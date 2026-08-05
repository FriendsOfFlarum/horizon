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

namespace FoF\Horizon\Providers;

use Flarum\Extension\ExtensionManager;
use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\Queue\QueueStatsProvider;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Horizon\BuiltInRouting;
use FoF\Horizon\DefaultProfiles;
use FoF\Horizon\Dispatcher\Notifier;
use FoF\Horizon\HorizonMetrics;
use FoF\Horizon\LayeredConfig;
use FoF\Horizon\Overrides\RedisQueue;
use FoF\Horizon\ProfileResolver;
use FoF\Horizon\Queue\HorizonQueueStatsProvider;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Notifications\Dispatcher as Notifications;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Uri;
use Laravel\Horizon\Events\LongWaitDetected;
use Laravel\Horizon\HorizonServiceProvider as Provider;
use Laravel\Horizon\SupervisorCommandString;
use Laravel\Horizon\WorkerCommandString;

class HorizonServiceProvider extends Provider
{
    public function register()
    {
        /** @var Paths $paths */
        $paths = resolve(Paths::class);

        if (!defined('HORIZON_PATH')) {
            define('HORIZON_PATH', realpath($paths->vendor.'/laravel/horizon'));
        }

        SupervisorCommandString::$command = str_replace('artisan', 'flarum', SupervisorCommandString::$command);
        WorkerCommandString::$command = str_replace('artisan', 'flarum', WorkerCommandString::$command);

        require_once __DIR__.'/../helpers.php';

        $this->configure();
    }

    public function boot()
    {
        $this->setupConfiguration($this->app);

        $this->registerServices();

        $this->registerQueueConnectors();
        $this->registerNotificationDispatcher();

        // We intentionally do not call parent::boot() here.
        // laravel/horizon v5.45.0 introduced an inline Route::middlewareGroup() call at the
        // top of parent::boot() which uses the Route facade. Flarum never sets a facade root,
        // so this throws "A facade root has not been set." instead of calling parent::boot()
        // we replicate only the two calls that fof/horizon actually needs.
        $this->normalizeConfig();
        $this->registerEvents();

        $this->registerQueueStatsProvider();
        $this->registerBuiltInQueues();
    }

    /**
     * Feed core's queue dashboard with a Horizon-enriched stats provider.
     *
     * fof/redis already binds a RedisQueueStatsProvider (pending / reserved /
     * failed from Redis); we extend it to add a `horizon` block (processes,
     * supervisors, throughput, wait, paused) that the Horizon-aware dashboard
     * widget renders — core's own widget ignores the extra key.
     *
     * Bound in boot() (not register/configure) so it runs after fof/redis's
     * own QueueStatsProvider binding — which is applied in the extender phase
     * from the site's extend.php — and therefore wins. Guarded so older cores
     * without the contract are untouched.
     */
    protected function registerQueueStatsProvider(): void
    {
        if (!interface_exists(QueueStatsProvider::class)) {
            return;
        }

        $this->app->singleton(QueueStatsProvider::class, function (Container $container) {
            return new HorizonQueueStatsProvider(
                $container->make('flarum.queue.connection'),
                $container->make('queue.failer'),
                $container->make('flarum.queue.queues'),
                $container->make(HorizonMetrics::class)
            );
        });
    }

    /**
     * Register the queues our built-in routing puts into use (mail, plus
     * realtime/gdpr when those extensions are enabled) into core's known-queues
     * registry, so the dashboard and per-queue pause cover them. Guarded so
     * older cores without the registry are untouched.
     */
    protected function registerBuiltInQueues(): void
    {
        if (!$this->app->bound('flarum.queue.queues')) {
            return;
        }

        $queues = (new BuiltInRouting(
            $this->app->make(ExtensionManager::class),
            $this->app->make('queue.routes')
        ))->activeQueues();

        $this->app->extend('flarum.queue.queues', function ($known) use ($queues) {
            return array_values(array_unique(array_merge(
                is_array($known) ? $known : ['default'],
                $queues
            )));
        });
    }

    /**
     * Resolve the "simultaneous outgoing emails" knob across every surface,
     * highest precedence first:
     *
     *   1. env       REDIS_HORIZON_EMAIL_CONCURRENCY
     *   2. config.php horizon.email_concurrency
     *   3. extend.php (new Horizon)->emailConcurrency(n)   [bound container value]
     *   4. admin UI  fof-horizon.email_concurrency setting
     *
     * Returns null when unset at every layer, in which case the emails profile
     * keeps its own default worker count. A non-numeric value throws, matching
     * the fail-fast behaviour of the rest of the scaling config.
     */
    protected function resolveEmailConcurrency(Container $container): ?int
    {
        // env + config.php (raw() handles these two, plus the settings layer —
        // but we want the extender to sit ABOVE settings, so we read settings
        // ourselves below rather than let raw() reach it first).
        $value = getenv('REDIS_HORIZON_EMAIL_CONCURRENCY');
        $value = ($value !== false && $value !== '') ? $value : null;

        if ($value === null) {
            $value = Arr::get($container->make('flarum.config')['horizon'] ?? [], 'email_concurrency');
        }

        if ($value === null && $container->bound('fof-horizon.email_concurrency')) {
            $value = $container->make('fof-horizon.email_concurrency');
        }

        if ($value === null) {
            $setting = $this->app->make(SettingsRepositoryInterface::class)->get('fof-horizon.email_concurrency');
            $value = ($setting !== null && $setting !== '') ? $setting : null;
        }

        if ($value === null) {
            return null;
        }

        if (!is_numeric($value) || (int) $value < 1) {
            throw new \InvalidArgumentException(
                'Email concurrency (REDIS_HORIZON_EMAIL_CONCURRENCY / horizon.email_concurrency / '
                ."emailConcurrency()) must be a positive integer, got: {$value}"
            );
        }

        return (int) $value;
    }

    protected function registerNotificationDispatcher(): void
    {
        if (!$this->app->bound(Notifications::class)) {
            $this->app->singleton(Notifications::class, function () {
                return new Notifier();
            });
        }
    }

    protected function registerRoutes()
    {
        // .. via extend.php
    }

    protected function configure()
    {
        $this->app->extend('flarum.queue.connection', function ($queue) {
            /** @var RedisManager $manager */
            $manager = $this->app->make(Factory::class);
            $queue = new RedisQueue($manager);
            /** @var Container $container */
            $container = resolve(Container::class);
            // @phpstan-ignore-next-line
            $queue->setContainer($container);

            // Give the connection a name so events that carry it (e.g.
            // WorkerIdle) receive a string rather than null — illuminate/queue
            // 13.15.0 type-hinted WorkerIdle::$connectionName as string, so a
            // null name throws a TypeError in the worker loop.
            $queue->setConnectionName('redis');

            return $queue;
        });

        // We intentionally do NOT override `queue.failer` here.
        //
        // Core binds a NullFailedJobProvider for non-database queues (failed
        // jobs silently discarded, queue:retry/queue:failed no-ops). fof/redis,
        // which fof/horizon depends on, already replaces that with a
        // Redis-backed failer (RedisFailedJobProvider). Deferring to it keeps
        // failed jobs in Redis — consistent with the rest of the stack and with
        // Horizon's own Redis failure store (JobRepository) — rather than
        // forcing them back into the database, which an operator on the Redis
        // stack has deliberately moved load away from. Core's dashboard and the
        // queue:failed/queue:retry commands work against that Redis failer; the
        // Horizon dashboard reads its own JobRepository independently.

        $this->app->afterResolving(Factory::class, function (RedisManager $manager) {
            if ($config = $manager->getConnectionConfig()) {
                $manager->addConnection('horizon', $config);
            }
        });

        $this->app->extend(CacheFactory::class, function () {
            return new class() implements CacheFactory {
                public function store($name = null)
                {
                    return resolve('cache.store');
                }

                /**
                 * @param string|null $driver
                 *
                 * @return mixed
                 */
                public function driver($driver = null)
                {
                    return $this->store($driver);
                }

                /**
                 * @param string            $name
                 * @param array<int, mixed> $arguments
                 *
                 * @return mixed
                 */
                public function __call($name, $arguments)
                {
                    return call_user_func_array([$this->store(), $name], $arguments);
                }
            };
        });

        $this->app->bind(BatchRepository::class, function () {
            $factory = resolve(BatchFactory::class);

            return new DatabaseBatchRepository(
                $factory,
                $this->app->make('db')->connection(),
                'batches'
            );
        });
    }

    protected function setupConfiguration(Container $container): void
    {
        /** @var Paths $paths */
        $paths = resolve(Paths::class);

        /** @var Config */
        $flarumConfig = resolve(Config::class);

        /** @var UrlGenerator */
        $url = resolve(UrlGenerator::class);

        /** @var SettingsRepositoryInterface $settings */
        $settings = resolve(SettingsRepositoryInterface::class);

        $env = $container->make('env');

        $config = include $paths->vendor.'/laravel/horizon/config/horizon.php';

        $path = (new Uri($url->to('admin')->base()))->getPath();

        // Every tunable below resolves through the supported configuration
        // layers, highest precedence first: environment variables
        // (REDIS_HORIZON_*), config.php ('horizon' key), admin settings,
        // then the baked-in default.
        $layered = new LayeredConfig($settings, $flarumConfig);

        Arr::set($config, 'env', $env);
        Arr::set($config, 'path', trim($path, '/').'/horizon');
        Arr::set($config, 'use', 'horizon');

        // A hand-written environments/supervisors array in config.php is not
        // applied by the profile system and would be silently dropped by the
        // exclusion below — fail loudly and point at the supported paths.
        $configHorizon = $container->make('flarum.config')['horizon'] ?? [];
        $offending = array_intersect(['environments', 'supervisors'], array_keys(is_array($configHorizon) ? $configHorizon : []));

        // `supervisors` IS the supported config.php surface for profiles, so it
        // is allowed; only a raw `environments` block is rejected here.
        if (in_array('environments', $offending, true)) {
            throw new \InvalidArgumentException(
                "fof/horizon ignores a hand-written 'environments' array in config.php; the worker layout "
                .'is managed by supervisor profiles. Configure profiles under the config.php '
                ."'horizon.supervisors' key, or, to take full manual control, use the Horizon extender's "
                .'useRawConfig() which bypasses the profile system.'
            );
        }

        // Escape hatch: useRawConfig() takes full manual control. The supplied
        // supervisor map is used verbatim as the current environment's layout;
        // profile assembly and auto-routing are skipped entirely.
        if ($container->bound('fof-horizon.raw_environments')) {
            $supervisors = $container->make('fof-horizon.raw_environments');

            Arr::set($config, 'environments', [$env => $supervisors]);

            // Horizon merges its `defaults` template into every environment; a
            // raw config takes responsibility for its own supervisors, so clear
            // it to avoid resurrecting the vendored supervisor-1 (see below).
            Arr::set($config, 'defaults', []);

            $this->finaliseConfig($container, $config, $layered);

            return;
        }

        // Assemble supervisors from the profile set. A site's Horizon extender
        // registers the (defaults + overrides) profiles under this binding; if
        // no extender ran, fall back to the built-in defaults so a config- or
        // env-only site still gets the tiered layout.
        $profiles = $container->bound('fof-horizon.profiles')
            ? $container->make('fof-horizon.profiles')
            : DefaultProfiles::all();

        // Wire Flarum's own queued work onto the built-in profiles: core mail
        // jobs onto the always-on `emails` profile, and — when their extensions
        // are enabled — realtime jobs onto `realtime` and gdpr jobs onto `long`
        // (bringing those tiers online). Runs before the config.php/env layers
        // below so an operator can still tune or override the result.
        $routing = new BuiltInRouting(
            $container->make(ExtensionManager::class),
            $container->make('queue.routes')
        );
        $profiles = $routing->apply($profiles);

        $resolver = new ProfileResolver($layered);

        // config.php may set non-scaling supervisor properties (queues, balance,
        // timeout, arbitrary Horizon keys) per profile under horizon.supervisors.
        // Apply those onto the profile before resolving. The scaling scalars
        // (processes/memory and their base/multiplier) are intentionally left to
        // the resolver, which reads them through LayeredConfig so env still wins.
        $configSupervisors = Arr::get($container->make('flarum.config')['horizon'] ?? [], 'supervisors', []);
        $scalingKeys = ['processes', 'memory', 'processesBase', 'processesMultiplier', 'memoryBase', 'memoryMultiplier'];

        // "Simultaneous outgoing emails" is a first-class knob: each email worker
        // sends one message at a time, so it maps onto the emails profile's
        // worker count. Resolve it across all surfaces (env > config.php >
        // extender > admin setting) and, when set, pin it as the emails profile's
        // process count. Passing it to the resolver as a forced value means it
        // wins over the generic REDIS_HORIZON_EMAILS_MAX_PROCESSES, so there is
        // one obvious control for email throughput rather than two.
        $emailConcurrency = $this->resolveEmailConcurrency($container);

        $supervisors = [];
        $maxMemory = 0;

        foreach ($profiles as $name => $profile) {
            if (!empty($configSupervisors[$name]) && is_array($configSupervisors[$name])) {
                $profile = $profile->with(Arr::except($configSupervisors[$name], $scalingKeys));
            }

            $forcedProcesses = ($name === 'emails') ? $emailConcurrency : null;

            $resolved = $resolver->resolve($profile, 'redis', $forcedProcesses);

            // A profile scaled to zero processes registers no supervisor.
            if ($resolved === null) {
                continue;
            }

            $supervisors['supervisor-'.$name] = $resolved;
            $maxMemory = max($maxMemory, (int) $resolved['memory']);
        }

        // A worker forked with a memory budget larger than the CLI
        // memory_limit hits PHP's fatal "Allowed memory size exhausted" before
        // Horizon's graceful memory check can restart it. Raise the worker
        // memory_limit to the largest supervisor budget so no site trips that
        // silent OOM. An explicit REDIS_HORIZON_MEMORY_LIMIT still wins.
        $memoryLimit = max($layered->integer('memory_limit', 128), $maxMemory);
        Arr::set($config, 'memory_limit', $memoryLimit);

        Arr::set($config, 'environments', [
            $env => $supervisors,
        ]);

        // The vendored horizon.php ships a `defaults` block containing a
        // `supervisor-1` template. Horizon's ProvisioningPlan merges `defaults`
        // INTO every environment (array_replace_recursive), so leaving it in
        // place resurrects `supervisor-1` alongside our profiles on every boot,
        // regardless of the assembled `environments`. Our profiles are already
        // fully resolved (each carries its own complete option set), so we do
        // not use Horizon's defaults mechanism — clear it.
        Arr::set($config, 'defaults', []);

        $this->finaliseConfig($container, $config, $layered);
    }

    /**
     * Apply the trim settings and persist the assembled horizon config, merging
     * in any other top-level keys from config.php / local extenders without
     * letting them clobber the sections we own.
     *
     * @param array<string, mixed> $config
     */
    protected function finaliseConfig(Container $container, array $config, LayeredConfig $layered): void
    {
        Arr::set($config, 'trim', [
            'recent'        => $layered->integer('trim.recent', 60),
            'pending'       => $layered->integer('trim.pending', 60),
            'completed'     => $layered->integer('trim.completed', 60),
            'recent_failed' => $layered->integer('trim.recent_failed', 10080),
            'failed'        => $layered->integer('trim.failed', 10080),
            'monitored'     => $layered->integer('trim.monitored', 10080),
        ]);

        /** @var Repository $repository */
        $repository = $container->make(Repository::class);

        $rawFlarumConfig = $container->make('flarum.config') ?? [];

        // Load existing config items and merge these with a possible key in the config.php.
        // Precedence: existing keys from local extenders, config.php and the default horizon.php.
        //
        // `environments`, `supervisors` and `trim` are assembled authoritatively
        // above (from the profile resolver / useRawConfig / LayeredConfig) and
        // MUST NOT be reintroduced from either source here:
        //   - `supervisors`/`trim` are consumed per-value, so a partial config.php
        //     override would otherwise clobber the assembled sections;
        //   - `environments` is the finished supervisor layout. array_merge is
        //     not recursive, so a stale `environments` in $existing (e.g. the
        //     vendored horizon.php default that still carries `supervisor-1`)
        //     would replace ours wholesale and resurrect that supervisor.
        $excluded = ['environments', 'supervisors', 'trim'];
        $existing = Arr::except($repository->get('horizon', []), $excluded);
        $config = array_merge($config, Arr::except($rawFlarumConfig['horizon'] ?? [], $excluded), $existing);

        $repository->set(['horizon' => $config]);
    }

    protected function registerEvents()
    {
        // Remove event listeners for Long wait because it uses the Laravel Notification facade.
        unset($this->events[LongWaitDetected::class]);

        parent::registerEvents();
    }

    public function defineAssetPublishing(): void
    {
    }

    protected function offerPublishing()
    {
    }

    protected function registerCommands()
    {
    }

    protected function registerResources()
    {
    }
}
