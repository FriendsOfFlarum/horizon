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

namespace FoF\Horizon;

use Flarum\Foundation\Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

/**
 * Reports which admin-configurable settings are actually pinned by a
 * higher-precedence layer (an environment variable, config.php, or the Horizon
 * extender in extend.php), so the admin UI can disable those inputs and explain
 * why editing them would have no effect.
 *
 * Precedence is env > config.php > extend.php > admin setting, so any value
 * present in a layer above the admin setting "locks" the field. Environment
 * variables are reported distinctly because they are unambiguously detectable;
 * config.php and extend.php both merge into the same assembled `horizon` config
 * at boot and cannot be reliably told apart afterwards, so they are grouped as
 * "code".
 */
class SettingLocks
{
    /**
     * Each admin setting mapped to the environment variable and config.php key
     * that can pin it. `email_concurrency` also has a dedicated extender
     * binding; that is handled specially in {@see locks()}.
     *
     * @var array<string, array{env: string, config: string}>
     */
    private const MAP = [
        'email_concurrency'  => ['env' => 'REDIS_HORIZON_EMAIL_CONCURRENCY', 'config' => 'email_concurrency'],
        'memory_limit'       => ['env' => 'REDIS_HORIZON_MEMORY_LIMIT', 'config' => 'memory_limit'],
        'trim.recent'        => ['env' => 'REDIS_HORIZON_TRIM_RECENT', 'config' => 'trim.recent'],
        'trim.pending'       => ['env' => 'REDIS_HORIZON_TRIM_PENDING', 'config' => 'trim.pending'],
        'trim.completed'     => ['env' => 'REDIS_HORIZON_TRIM_COMPLETED', 'config' => 'trim.completed'],
        'trim.recent_failed' => ['env' => 'REDIS_HORIZON_TRIM_RECENT_FAILED', 'config' => 'trim.recent_failed'],
        'trim.failed'        => ['env' => 'REDIS_HORIZON_TRIM_FAILED', 'config' => 'trim.failed'],
        'trim.monitored'     => ['env' => 'REDIS_HORIZON_TRIM_MONITORED', 'config' => 'trim.monitored'],
    ];

    public function __construct(
        protected Config $config,
        protected Container $container
    ) {
    }

    /**
     * A map of locked settings → { source: 'env'|'code', value: mixed }, keyed
     * by the setting suffix (e.g. "email_concurrency", "trim.recent"). Settings
     * not pinned by any higher layer are absent.
     *
     * @return array<string, array{source: string, value: mixed}>
     */
    public function locks(): array
    {
        $horizonConfig = $this->config->offsetGet('horizon') ?? [];
        $locks = [];

        foreach (self::MAP as $key => $sources) {
            $env = getenv($sources['env']);

            if ($env !== false && $env !== '') {
                $locks[$key] = ['source' => 'env', 'value' => $env];
                continue;
            }

            $fromConfig = Arr::get($horizonConfig, $sources['config']);

            if ($fromConfig !== null) {
                $locks[$key] = ['source' => 'code', 'value' => $fromConfig];
                continue;
            }

            // email_concurrency has a dedicated extender method that binds a
            // container value rather than writing into the horizon config.
            if ($key === 'email_concurrency' && $this->container->bound('fof-horizon.email_concurrency')) {
                $locks[$key] = ['source' => 'code', 'value' => $this->container->make('fof-horizon.email_concurrency')];
            }
        }

        return $locks;
    }
}
