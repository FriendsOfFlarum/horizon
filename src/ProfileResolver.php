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

use InvalidArgumentException;

/**
 * Turns a {@see Supervisor} profile into the concrete `environments` entry
 * Horizon consumes, resolving its process count and memory through the
 * base × multiplier model.
 *
 * Each scaling input is resolved through {@see LayeredConfig} (env > config.php
 * > settings > the profile's own default), so a site can tune a tier from any
 * layer. The environment keys follow the pattern the GlowingBlue image
 * established, namespaced to Horizon so they never collide with fof/redis's
 * connection variables:
 *
 *   REDIS_HORIZON_<PROFILE>_MAX_PROCESSES        literal process count (wins outright)
 *   REDIS_HORIZON_<PROFILE>_PROCESSES_MULTIPLIER base is multiplied by this
 *   REDIS_HORIZON_<PROFILE>_MEMORY_LIMIT         literal per-process memory (MB)
 *   REDIS_HORIZON_<PROFILE>_MEMORY_MULTIPLIER    base memory is multiplied by this
 *
 * A non-numeric or negative value for any of these throws
 * {@see InvalidArgumentException} at boot: a misconfigured worker fleet should
 * fail loudly, not silently fall back to a default and leave an operator
 * wondering why their scaling never took effect.
 *
 * A resolved process count of 0 yields null — the tier is defined but registers
 * no supervisor ("scale to zero").
 */
class ProfileResolver
{
    public function __construct(
        protected LayeredConfig $config
    ) {
    }

    /**
     * @param int|null $forcedProcesses a process count that overrides all
     *                                   scaling layers (env/config/base×mult),
     *                                   used for dedicated knobs such as email
     *                                   concurrency. Null means resolve normally.
     *
     * @return array<string, mixed>|null the Horizon supervisor config, or null if scaled to zero
     */
    public function resolve(Supervisor $profile, string $connection = 'redis', ?int $forcedProcesses = null): ?array
    {
        $processes = $forcedProcesses
            ?? $this->scale($profile->name, 'PROCESSES', 'processes', $profile->processesBase, $profile->processesMultiplier);

        if ($processes <= 0) {
            return null;
        }

        // A tier with workers but no queues would silently fall back to draining
        // `default` (Horizon coerces an empty --queue to default), duplicating
        // the standard tier rather than doing anything useful. That is a
        // misconfiguration — a tier brought online must have at least one queue
        // routed to it. Fail loudly rather than spawn a pointless worker pool.
        if (empty($profile->queues)) {
            throw new InvalidArgumentException(
                "Supervisor '{$profile->name}' has {$processes} process(es) but no queues. "
                ."Route at least one queue to it (e.g. via routeJob()/queueOn() or its 'queues' config) "
                ."or leave it scaled to zero."
            );
        }

        $memory = $this->scale($profile->name, 'MEMORY', 'memory', $profile->memoryBase, $profile->memoryMultiplier);

        $supervisor = array_merge($profile->overrides, [
            'connection' => $connection,
            'queue'      => $profile->queues,
            'balance'    => $profile->balance,
            'processes'  => $processes,
            'tries'      => $profile->tries,
            'timeout'    => $profile->timeout,
            'memory'     => $memory,
        ]);

        return $supervisor;
    }

    /**
     * Resolve a base × multiplier scaling figure for one profile axis
     * (processes or memory).
     *
     * A literal MAX/LIMIT value, from any layer, wins outright. Otherwise the
     * base is multiplied by the multiplier. Every input is validated as a
     * positive integer; anything else throws.
     *
     * @param string $env      the env-key axis fragment (PROCESSES|MEMORY)
     * @param string $settings the config/settings axis fragment (processes|memory)
     */
    protected function scale(string $profile, string $env, string $settings, int $base, int $multiplier): int
    {
        $upper = strtoupper($profile);
        $lower = strtolower($profile);

        $literalKey = $env === 'PROCESSES' ? 'MAX_PROCESSES' : 'MEMORY_LIMIT';

        // A literal count/limit short-circuits the base × multiplier.
        $literal = $this->config->raw(
            "REDIS_HORIZON_{$upper}_{$literalKey}",
            "supervisors.{$lower}.{$settings}"
        );

        if ($literal !== null) {
            return $this->positiveInt($literal, "REDIS_HORIZON_{$upper}_{$literalKey}");
        }

        $resolvedBase = $this->config->raw(
            "REDIS_HORIZON_{$upper}_{$literalKey}_BASE",
            "supervisors.{$lower}.{$settings}Base"
        );
        $resolvedBase = $resolvedBase !== null
            ? $this->positiveInt($resolvedBase, "REDIS_HORIZON_{$upper}_{$literalKey}_BASE")
            : $base;

        $multiplierKey = $env === 'PROCESSES' ? 'PROCESSES_MULTIPLIER' : 'MEMORY_MULTIPLIER';

        $resolvedMultiplier = $this->config->raw(
            "REDIS_HORIZON_{$upper}_{$multiplierKey}",
            "supervisors.{$lower}.{$settings}Multiplier"
        );
        $resolvedMultiplier = $resolvedMultiplier !== null
            ? $this->positiveInt($resolvedMultiplier, "REDIS_HORIZON_{$upper}_{$multiplierKey}", allowZero: true)
            : $multiplier;

        return $resolvedBase * $resolvedMultiplier;
    }

    /**
     * @param mixed $value
     */
    protected function positiveInt($value, string $source, bool $allowZero = false): int
    {
        if (!is_numeric($value) || (string) (int) $value !== ltrim((string) $value, '+')) {
            throw new InvalidArgumentException("{$source} must be an integer, got: ".var_export($value, true));
        }

        $int = (int) $value;

        if ($int < 0 || (!$allowZero && $int === 0)) {
            throw new InvalidArgumentException(
                "{$source} must be a ".($allowZero ? 'non-negative' : 'positive')." integer, got: {$int}"
            );
        }

        return $int;
    }
}
