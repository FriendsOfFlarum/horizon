<?php

/*
 * This file is part of fof/horizon.
 *
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
 *  1. Environment variables — FOF_HORIZON_<KEY> (e.g. FOF_HORIZON_PROCESSES,
 *     FOF_HORIZON_TRIM_COMPLETED). The `supervisor.` prefix is dropped from
 *     the variable name.
 *  2. config.php — under the `horizon` key (e.g. horizon.supervisor.processes).
 *  3. Admin settings — `fof-horizon.<key>`.
 *  4. The given default.
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
        $env = getenv($this->envKey($key));

        if ($env !== false && $env !== '') {
            return $env;
        }

        $fromConfig = Arr::get($this->config->offsetGet('horizon') ?? [], $key);

        if ($fromConfig !== null) {
            return $fromConfig;
        }

        $fromSettings = $this->settings->get('fof-horizon.'.$key);

        if ($fromSettings !== null && $fromSettings !== '') {
            return $fromSettings;
        }

        return $default;
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

        return 'FOF_HORIZON_'.strtoupper(str_replace('.', '_', $key));
    }
}
