<?php

/*
 * This file is part of fof/horizon.
 *
 * Copyright (c) Bokt.
 * Copyright (c) Blomstra Ltd.
 * Copyright (c) FriendsOfFlarum
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use FoF\Horizon\DefaultProfiles;
use FoF\Horizon\Supervisor;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

/**
 * Configure fof/horizon's worker supervisors, job routing and raw Horizon
 * config from an extension or a site's extend.php.
 *
 * The fluent surface is layered so simple sites stay simple and large ones keep
 * full control:
 *
 *   // Route a job onto a tier. Appends the job's queue to that supervisor and
 *   // registers it so admin tooling (dashboard, per-queue pause) covers it.
 *   (new Horizon)
 *       ->routeJob(RealtimeJob::class, 'fast')
 *       ->routeJob(ExportJob::class, 'long');
 *
 *   // Add extra queues under an existing supervisor without touching its
 *   // other settings (base images do this a lot).
 *   (new Horizon)->queueOn('long', 'gamilytics', 'migration-high');
 *
 *   // Override a tier's knobs. Unknown keys pass straight through to Horizon.
 *   (new Horizon)->supervisor('fast', ['processes' => 24, 'nice' => -5]);
 *
 *   // Define a brand-new tier.
 *   (new Horizon)->supervisor('media', ['queues' => ['thumbnails'], 'timeout' => 300]);
 *
 * ->config()/->environment() remain as raw escape hatches for anything the
 * builder does not model.
 */
class Horizon implements ExtenderInterface
{
    /**
     * The known-queues registry key core exposes; extensions append their
     * named queues here so admin tooling can offer per-queue controls.
     */
    private const QUEUE_REGISTRY = 'flarum.queue.queues';

    /** @var array<string, mixed>|null */
    private ?array $config = null;

    /** @var array<string, mixed>|null */
    private ?array $environment = null;

    /**
     * Per-supervisor override sets, applied over the default profiles.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $supervisors = [];

    /**
     * Extra queues to append to a supervisor's queue list, keyed by supervisor.
     *
     * @var array<string, array<int, string>>
     */
    private array $appendQueues = [];

    /**
     * Job class => queue routing. Applied as AbstractJob::$onQueue at boot,
     * guarded by class_exists.
     *
     * @var array<class-string, string>
     */
    private array $routes = [];

    /**
     * Whether to seed the built-in default profiles. On by default; a site that
     * wants a blank slate can opt out.
     */
    private bool $useDefaults = true;

    /**
     * The number of simultaneous outgoing emails, if set here. Feeds the emails
     * profile's worker count. Null means "not set at this layer".
     */
    private ?int $emailConcurrency = null;

    public function extend(Container $container, ?Extension $extension = null): void
    {
        /** @var Repository $repository */
        $repository = $container->make(Repository::class);

        if ($this->config) {
            $repository->set('horizon', $this->config);
        }

        if ($this->environment) {
            $repository->set("horizon.environments.{$container->make('env')}", $this->environment);
        }

        if ($this->emailConcurrency !== null) {
            $container->instance('fof-horizon.email_concurrency', $this->emailConcurrency);
        }

        $this->registerProfiles($container);
        $this->registerRoutedQueues($container);
        $this->applyJobRoutes();
    }

    /**
     * Assemble the profile set (defaults + site overrides + appended queues)
     * and bind it for the service provider to turn into Horizon's `environments`.
     */
    private function registerProfiles(Container $container): void
    {
        /** @var array<string, Supervisor> $profiles */
        $profiles = $this->useDefaults ? DefaultProfiles::all() : [];

        // Define or override supervisors named by the site.
        foreach ($this->supervisors as $name => $overrides) {
            $base = $profiles[$name] ?? new Supervisor(name: $name);
            $profiles[$name] = $base->with($overrides);
        }

        // Append routed/explicit queues onto their supervisors, de-duplicated
        // and without disturbing any other settings.
        foreach ($this->appendQueues as $name => $queues) {
            $profile = $profiles[$name] ?? new Supervisor(name: $name, queues: []);
            $profile->queues = array_values(array_unique(array_merge($profile->queues, $queues)));
            $profiles[$name] = $profile;
        }

        // Merge with anything a previously-run Horizon extender registered, so
        // multiple extenders across extensions compose rather than clobber.
        if ($container->bound('fof-horizon.profiles')) {
            /** @var array<string, Supervisor> $existing */
            $existing = $container->make('fof-horizon.profiles');
            $profiles = array_merge($existing, $profiles);
        }

        $container->instance('fof-horizon.profiles', $profiles);
    }

    /**
     * Register every queue any supervisor serves — plus explicitly routed
     * ones — into core's known-queues registry so the dashboard and per-queue
     * pause cover them. Guarded: older cores without the registry are untouched.
     */
    private function registerRoutedQueues(Container $container): void
    {
        if (!$container->bound(self::QUEUE_REGISTRY)) {
            return;
        }

        $queues = array_merge(
            array_values($this->routes),
            ...array_values($this->appendQueues)
        );

        foreach ($this->supervisors as $overrides) {
            if (isset($overrides['queues'])) {
                $list = is_array($overrides['queues'])
                    ? $overrides['queues']
                    : array_map('trim', explode(',', (string) $overrides['queues']));
                $queues = array_merge($queues, $list);
            }
        }

        if (empty($queues)) {
            return;
        }

        $container->extend(self::QUEUE_REGISTRY, function ($known) use ($queues) {
            return array_values(array_unique(array_merge(
                is_array($known) ? $known : ['default'],
                $queues
            )));
        });
    }

    /**
     * Apply the job => queue routes as AbstractJob::$onQueue, skipping any job
     * class that is not installed so an optional dependency can't fatal boot.
     */
    private function applyJobRoutes(): void
    {
        foreach ($this->routes as $class => $queue) {
            if (class_exists($class)) {
                $class::$onQueue = $queue;
            }
        }
    }

    /**
     * Define or override a supervisor profile. Recognised keys (queues,
     * processes, memory, tries, timeout, balance, and the base/multiplier
     * variants) map onto the profile; anything else — nice, balanceMaxShift,
     * retry_after, … — is passed straight through to Horizon.
     *
     * @param array<string, mixed> $overrides
     */
    public function supervisor(string $name, array $overrides = []): self
    {
        $this->supervisors[$name] = array_merge($this->supervisors[$name] ?? [], $overrides);

        return $this;
    }

    /**
     * Append one or more queues to a supervisor's queue list without altering
     * its other settings. This is how a base image adds its own queues under,
     * say, the `long` supervisor. Also registers them in the known-queues
     * registry.
     */
    public function queueOn(string $supervisor, string ...$queues): self
    {
        $this->appendQueues[$supervisor] = array_merge(
            $this->appendQueues[$supervisor] ?? [],
            array_values($queues)
        );

        return $this;
    }

    /**
     * Route a job onto a queue: sets AbstractJob::$onQueue at boot (guarded by
     * class_exists), and if that queue is served by a named supervisor, ensures
     * the supervisor lists it. Always registers the queue for admin tooling.
     *
     * @param class-string $jobClass
     */
    public function routeJob(string $jobClass, string $queue, ?string $supervisor = null): self
    {
        $this->routes[$jobClass] = $queue;

        if ($supervisor !== null) {
            $this->queueOn($supervisor, $queue);
        }

        return $this;
    }

    /**
     * Opt out of the built-in standard/fast/long/emails profiles, starting from
     * a blank supervisor set (only what this extender defines will exist).
     */
    public function withoutDefaultProfiles(): self
    {
        $this->useDefaults = false;

        return $this;
    }

    /**
     * Cap the number of simultaneous outgoing emails.
     *
     * Each email worker sends one message at a time, so this is simply the
     * `emails` profile's worker count — set it to your mail provider's
     * concurrent-connection limit. This is the highest-priority way to set it
     * short of an environment variable, and it takes precedence over a generic
     * `emails` process count.
     */
    public function emailConcurrency(int $concurrency): self
    {
        $this->emailConcurrency = $concurrency;

        return $this;
    }

    /**
     * Use a configuration file or array to configure Horizon.
     *
     * @param string|array $config
     *
     * @return Horizon
     */
    public function config($config)
    {
        if (is_string($config)) {
            $this->config = include $config;
        } else {
            $this->config = (array) $config;
        }

        return $this;
    }

    /**
     * @param array|string $config
     *
     * @return $this
     */
    public function environment($config)
    {
        if (is_string($config)) {
            $this->environment = include $config;
        } else {
            $this->environment = (array) $config;
        }

        return $this;
    }
}
