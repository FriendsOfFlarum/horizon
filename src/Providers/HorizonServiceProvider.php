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
use Flarum\Settings\SettingsRepositoryInterface;
use FoF\Horizon\Dispatcher\Notifier;
use FoF\Horizon\Overrides\RedisQueue;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Bus\BatchFactory;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\DatabaseBatchRepository;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository;
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

        parent::boot();
    }

    protected function registerNotificationDispatcher()
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
            /** @phpstan-ignore-next-line */
            $queue->setContainer($this->app);

            return $queue;
        });

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

                public function driver($driver = null)
                {
                    return $this->store($driver);
                }

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

    protected function setupConfiguration($container)
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

        Arr::set($config, 'env', $env);
        Arr::set($config, 'path', trim($path, '/').'/horizon');
        Arr::set($config, 'use', 'horizon');
        Arr::set($config, 'environments', [
            $env => [
                'supervisor-1' => [
                    'connection' => 'redis',
                    'queue'      => ['default'],
                    'balance'    => 'auto',
                    'processes'  => 4,
                    'tries'      => 3,
                ],
            ],
        ]);

        Arr::set($config, 'trim', [
            'recent'        => $settings->get('fof-horizon.trim.recent'),
            'pending'       => $settings->get('fof-horizon.trim.pending'),
            'completed'     => $settings->get('fof-horizon.trim.completed'),
            'recent_failed' => $settings->get('fof-horizon.trim.recent_failed'),
            'failed'        => $settings->get('fof-horizon.trim.failed'),
            'monitored'     => $settings->get('fof-horizon.trim.monitored'),
        ]);

        /** @var Repository $repository */
        $repository = $container->make(Repository::class);

        $flarumConfig = $container->make('flarum.config') ?? [];

        // Load existing config items and merge these with a possible key in the config.php.
        // Precedence: existing keys from local extenders, config.php and the default horizon.php.
        $existing = $repository->get('horizon', []);
        $config = array_merge($config, $flarumConfig['horizon'] ?? [], $existing);

        $repository->set(['horizon' => $config]);
    }

    protected function registerEvents()
    {
        // Remove event listeners for Long wait because it uses the Laravel Notification facade.
        unset($this->events[LongWaitDetected::class]);

        parent::registerEvents();
    }

    public function defineAssetPublishing()
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
