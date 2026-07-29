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
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Support\Arr;

/**
 * Resolves an fof/horizon configuration value through the supported layers,
 * highest precedence first:
 *
 *  1. Environment variables. Global tunables use REDIS_HORIZON_<KEY> (e.g.
 *     REDIS_HORIZON_MEMORY_LIMIT, REDIS_HORIZON_TRIM_COMPLETED); per-profile
 *     scaling uses the explicit keys resolved by {@see ProfileResolver}. All
 *     Horizon env vars share the REDIS_HORIZON_ prefix so they group with
 *     fof/redis's REDIS_ connection vars without colliding with them.
 *  2. config.php — under the `horizon` key (e.g. horizon.supervisors.long.processes).
 *  3. Admin settings — `fof-horizon.<key>`.
 *  4. The given default.
 *
 * Prior to the profile rework these keys were prefixed FOF_HORIZON_; that
 * prefix is retired. See the upgrade notes for the migration.
 */
class LayeredConfig
{
    public function __construct(
        protected SettingsRepositoryInterface $settings,
        protected Config $config
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->raw($this->envKey($key), $key);

        return $value ?? $default;
    }

    /**
     * Look a value up across env → config.php → settings, returning null if it
     * is absent from every layer (so callers can tell "unset" from a real 0).
     *
     * Both keys are given explicitly because the env and config vocabularies
     * differ for per-profile scaling: the env side uses REDIS_HORIZON_* while
     * config.php/settings use dotted `horizon.*` / `fof-horizon.*` paths.
     *
     * @param string      $envKey    the full environment variable name
     * @param string|null $configKey dotted path under the `horizon` config key
     *                               and `fof-horizon.` settings prefix
     */
    public function raw(string $envKey, ?string $configKey = null): mixed
    {
        $env = getenv($envKey);

        if ($env !== false && $env !== '') {
            return $env;
        }

        if ($configKey !== null) {
            $fromConfig = Arr::get($this->config->offsetGet('horizon') ?? [], $configKey);

            if ($fromConfig !== null) {
                return $fromConfig;
            }

            $fromSettings = $this->settings->get('fof-horizon.'.$configKey);

            if ($fromSettings !== null && $fromSettings !== '') {
                return $fromSettings;
            }
        }

        return null;
    }

    public function integer(string $key, int $default): int
    {
        return (int) $this->get($key, $default);
    }

    public function string(string $key, string $default): string
    {
        return (string) $this->get($key, $default);
    }

    /**
     * Resolve a value that may be an array (config.php) or a comma-separated
     * string (settings UI, environment variables).
     *
     * @return array<int, string>
     */
    public function list(string $key, array $default): array
    {
        $value = $this->get($key, $default);

        if (is_array($value)) {
            return array_values($value);
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
    }

    protected function envKey(string $key): string
    {
        if (str_starts_with($key, 'supervisor.')) {
            $key = substr($key, strlen('supervisor.'));
        }

        return 'REDIS_HORIZON_'.strtoupper(str_replace('.', '_', $key));
    }
}
