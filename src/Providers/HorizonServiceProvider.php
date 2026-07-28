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

use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Http\UrlGenerator;
use Flarum\Queue\QueueStatsProvider;
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Horizon\Dispatcher\Notifier;
use FoF\Horizon\HorizonMetrics;
use FoF\Horizon\LayeredConfig;
use FoF\Horizon\Overrides\RedisQueue;
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
        // (FOF_HORIZON_*), config.php ('horizon' key), admin settings,
        // then the baked-in default.
        $layered = new LayeredConfig($settings, $flarumConfig);

        Arr::set($config, 'env', $env);
        Arr::set($config, 'path', trim($path, '/').'/horizon');
        Arr::set($config, 'use', 'horizon');
        Arr::set($config, 'memory_limit', $layered->integer('memory_limit', 128));

        Arr::set($config, 'environments', [
            $env => [
                'supervisor-1' => [
                    'connection' => 'redis',
                    'queue'      => $layered->list('supervisor.queues', ['default']),
                    'balance'    => $layered->string('supervisor.balance', 'auto'),
                    'processes'  => $layered->integer('supervisor.processes', 4),
                    'tries'      => $layered->integer('supervisor.tries', 3),
                    'memory'     => $layered->integer('supervisor.memory', 128),
                ],
            ],
        ]);

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
        // The supervisor and trim keys are consumed per-value by LayeredConfig
        // above, so they are excluded from this wholesale merge — otherwise a
        // partial config.php override would clobber the assembled sections.
        $existing = $repository->get('horizon', []);
        $config = array_merge($config, Arr::except($rawFlarumConfig['horizon'] ?? [], ['supervisor', 'trim']), $existing);

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
