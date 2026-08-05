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

/**
 * A single supervisor profile: the baked-in defaults for one tier (standard,
 * fast, realtime, long, emails, or a site-defined one), plus whatever extra Horizon keys
 * a site chose to pass through.
 *
 * Scaling is expressed as a base × multiplier for both process count and
 * memory, mirroring the model every production site converged on. `processes`
 * and `memory` here are the *defaults*; the actual values are resolved at boot
 * by {@see ProfileResolver}, which lets environment variables and config.php
 * override either the resolved figure or the base/multiplier that produces it.
 *
 * A profile whose resolved process count is 0 registers no supervisor at all
 * ("scale to zero"): the tier exists and can be switched on later by raising
 * its process count, but costs nothing until then.
 */
class Supervisor
{
    /**
     * @param array<string, mixed> $overrides arbitrary Horizon keys passed straight through
     */
    public function __construct(
        public string $name,
        public array $queues = ['default'],
        public int $processesBase = 1,
        public int $processesMultiplier = 1,
        public int $memoryBase = 128,
        public int $memoryMultiplier = 1,
        public int $tries = 1,
        public int $timeout = 60,
        public string $balance = 'auto',
        public array $overrides = [],
    ) {
    }

    /**
     * Apply a partial set of overrides, returning a new instance. Recognised
     * keys map onto the typed properties; anything else is preserved verbatim
     * in {@see $overrides} so it reaches Horizon unchanged (nice, balanceMaxShift,
     * retry_after, …).
     *
     * @param array<string, mixed> $overrides
     */
    public function with(array $overrides): self
    {
        $clone = clone $this;

        foreach ($overrides as $key => $value) {
            switch ($key) {
                case 'queues':
                    $clone->queues = is_array($value)
                        ? array_values($value)
                        : array_values(array_filter(array_map('trim', explode(',', (string) $value)), 'strlen'));
                    break;
                case 'processes':
                    // A literal process count overrides the base×multiplier.
                    $clone->processesBase = (int) $value;
                    $clone->processesMultiplier = 1;
                    break;
                case 'processesBase':
                    $clone->processesBase = (int) $value;
                    break;
                case 'processesMultiplier':
                    $clone->processesMultiplier = (int) $value;
                    break;
                case 'memory':
                    $clone->memoryBase = (int) $value;
                    $clone->memoryMultiplier = 1;
                    break;
                case 'memoryBase':
                    $clone->memoryBase = (int) $value;
                    break;
                case 'memoryMultiplier':
                    $clone->memoryMultiplier = (int) $value;
                    break;
                case 'tries':
                    $clone->tries = (int) $value;
                    break;
                case 'timeout':
                    $clone->timeout = (int) $value;
                    break;
                case 'balance':
                    $clone->balance = (string) $value;
                    break;
                default:
                    $clone->overrides[$key] = $value;
            }
        }

        return $clone;
    }
}
