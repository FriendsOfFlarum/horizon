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
 * The built-in supervisor profiles fof/horizon ships out of the box.
 *
 * These encode the timeouts, priorities and scaling ratios that every serious
 * production Flarum site independently converged on, so a fresh install gets a
 * sane tiered worker layout with zero configuration:
 *
 *  - standard  Regular queue jobs — ordinary background work with no special
 *              timing characteristics. The base the others scale from.
 *  - fast      Jobs that (a) run quickly and (b) would cause problems if left
 *              in a long-running lane — they back up and starve other work. The
 *              short timeout is the protection: a "fast" job that hangs is
 *              killed early rather than clogging the lane.
 *  - long      Heavy lifting — slow, resource-hungry jobs (exports, GDPR,
 *              migrations). Long timeout, few fat workers, gentle priority.
 *  - emails    Outbound mail; capped by the mail provider's connection budget
 *              rather than host headroom, so it does NOT scale off the standard
 *              base — its worker count is the number of simultaneous sends
 *              (see the email-concurrency knob) and it retries transient
 *              failures.
 *
 * `fast` and `long` default their process count to 0 — defined but dormant
 * ("scale to zero") — and come online when their queues are routed (see
 * BuiltInRouting) or an operator raises them. `standard` and `emails` are
 * active by default.
 */
class DefaultProfiles
{
    /**
     * @return array<string, Supervisor>
     */
    public static function all(): array
    {
        return [
            'standard' => new Supervisor(
                name: 'standard',
                queues: ['default'],
                processesBase: 1,
                processesMultiplier: 6,
                memoryBase: 128,
                memoryMultiplier: 1,
                tries: 1,
                timeout: 60,
                balance: 'auto',
                overrides: [
                    'nice'            => 5,
                    'balanceMaxShift' => 5,
                    'balanceCooldown' => 3,
                ],
            ),
            'fast' => new Supervisor(
                name: 'fast',
                queues: [],
                // Dormant until a site routes queues here and gives it workers.
                processesBase: 0,
                processesMultiplier: 12,
                memoryBase: 128,
                memoryMultiplier: 1,
                tries: 1,
                timeout: 3,
                balance: 'auto',
                overrides: [
                    'nice'            => 0,
                    'balanceMaxShift' => 5,
                    'balanceCooldown' => 3,
                ],
            ),
            'long' => new Supervisor(
                name: 'long',
                queues: [],
                processesBase: 0,
                processesMultiplier: 1,
                memoryBase: 128,
                memoryMultiplier: 4,
                tries: 1,
                timeout: 3600,
                balance: 'simple',
                overrides: [
                    'nice'            => 10,
                    'balanceMaxShift' => 1,
                    'balanceCooldown' => 3,
                    'retry_after'     => 1800,
                ],
            ),
            'emails' => new Supervisor(
                name: 'emails',
                // Every install sends mail, so this profile is active out of the
                // box and serves the `mail` queue that core's mail jobs are
                // routed onto (see HorizonServiceProvider::applyBuiltInRouting).
                queues: ['mail'],
                // Capped by the mail server's connection budget, not host
                // headroom: a small fixed default, no host-derived multiplier.
                processesBase: 1,
                processesMultiplier: 1,
                memoryBase: 128,
                memoryMultiplier: 1,
                // Mail failures are often transient (greylisting, rate limits),
                // so unlike the other tiers this one retries.
                tries: 3,
                timeout: 120,
                balance: 'auto',
            ),
        ];
    }
}
