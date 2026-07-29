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
 * To hand-write the whole worker layout instead of using profiles, use
 * useRawConfig(). ->config() remains for other top-level Horizon keys but can
 * no longer set the environments/supervisors layout (it throws if you try).
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
     * Job class => queue routing. Registered in core's queue-route map at boot,
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

    /**
     * A verbatim Horizon `environments` array supplied via useRawConfig(). When
     * set, the profile system is bypassed entirely and this is used as-is.
     *
     * @var array<string, mixed>|null
     */
    private ?array $rawConfig = null;

    public function extend(Container $container, ?Extension $extension = null): void
    {
        /** @var Repository $repository */
        $repository = $container->make(Repository::class);

        if ($this->config) {
            $this->rejectLegacyEnvironments($this->config, '->config()');
            $repository->set('horizon', $this->config);
        }

        if ($this->environment) {
            // ->environment() only ever wrote to horizon.environments, which the
            // profile system now owns — so any use of it is a legacy supervisor
            // layout that would be silently dropped. Send the operator to the
            // supported path rather than ignore their configuration.
            $this->rejectLegacyEnvironments(['environments' => $this->environment], '->environment()');
        }

        if ($this->rawConfig !== null) {
            $container->instance('fof-horizon.raw_environments', $this->rawConfig);
        }

        if ($this->emailConcurrency !== null) {
            $container->instance('fof-horizon.email_concurrency', $this->emailConcurrency);
        }

        $this->registerProfiles($container);
        $this->registerRoutedQueues($container);
        $this->applyJobRoutes($container);
    }

    /**
     * The profile model owns the Horizon `environments`/`supervisors` layout, so
     * a hand-written one passed through the legacy ->config()/->environment()
     * escape hatches would be silently discarded. Fail loudly and point at the
     * supported paths instead of leaving a site running the wrong worker layout.
     *
     * @param array<string, mixed> $config
     */
    private function rejectLegacyEnvironments(array $config, string $via): void
    {
        $offending = array_intersect(['environments', 'supervisors'], array_keys($config));

        if (!empty($offending)) {
            throw new \InvalidArgumentException(
                "fof/horizon no longer applies a hand-written '".implode("'/'", $offending)."' array passed via {$via}; "
                .'the worker layout is managed by supervisor profiles. Configure profiles with '
                .'supervisor()/routeJob()/queueOn(), or, to take full manual control, pass your '
                .'environments array to useRawConfig() which bypasses the profile system.'
            );
        }
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
     * Register the job => queue routes in core's queue-route map, skipping any
     * job class that is not installed so an optional dependency can't fatal boot.
     */
    private function applyJobRoutes(Container $container): void
    {
        if (empty($this->routes)) {
            return;
        }

        $routes = $container->make('queue.routes');

        foreach ($this->routes as $class => $queue) {
            if (class_exists($class)) {
                $routes->set($class, $queue);
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
     * Route a job onto a queue: registers it in core's queue-route map at boot
     * (guarded by class_exists), and if that queue is served by a named
     * supervisor, ensures the supervisor lists it. Always registers the queue
     * for admin tooling.
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
     * Take full manual control of Horizon's worker layout, bypassing the
     * supervisor-profile system entirely — no default profiles, no automatic
     * job routing.
     *
     * Pass the supervisor map for the current environment (supervisor name =>
     * options). fof/horizon keys it under the running environment for you, so
     * you don't have to know or hardcode whether that's "production",
     * "testing", etc:
     *
     *   (new Horizon)->useRawConfig([
     *       'supervisor-1' => [
     *           'connection' => 'redis',
     *           'queue'      => ['default'],
     *           'balance'    => 'auto',
     *           'maxProcesses' => 10,
     *           // ...any Laravel Horizon supervisor options
     *       ],
     *   ]);
     *
     * @param array<string, array<string, mixed>> $supervisors supervisor name => options
     */
    public function useRawConfig(array $supervisors): self
    {
        $this->rawConfig = $supervisors;

        return $this;
    }

    /**
     * Use a configuration file or array to configure Horizon.
     *
     * Note: this cannot set the worker layout (`environments`/`supervisors`) —
     * that is owned by the profile system; use supervisor()/routeJob() or
     * useRawConfig() for that. It remains useful for other top-level Horizon
     * keys (e.g. `waits`, `fast_termination`).
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
