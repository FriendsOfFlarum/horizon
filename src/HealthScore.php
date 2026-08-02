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
 * Derives the dashboard health score from same-moment observations only —
 * never from counts covering different time windows. Each deduction is
 * returned as a factor so the UI can explain the number.
 *
 * The possible impacts sum to at most -100, so the score always equals
 * 100 plus the sum of the returned impacts.
 */
class HealthScore
{
    /**
     * @return array{score: int, factors: array<int, array{key: string, impact: int, value: string}>}
     */
    public function calculate(string $status, ?float $maxWait, ?string $maxWaitQueue, ?float $memoryPercentage): array
    {
        $factors = [];

        if ($status === 'inactive') {
            $factors[] = ['key' => 'status', 'impact' => -50, 'value' => 'inactive'];
        } elseif ($status === 'paused') {
            $factors[] = ['key' => 'status', 'impact' => -20, 'value' => 'paused'];
        }

        // $maxWait is in SECONDS (see HorizonMetrics::maxWait). Over five
        // minutes of backlog is degraded; over one minute is worth noting.
        if ($maxWait !== null && $maxWait > 300) {
            $factors[] = ['key' => 'wait', 'impact' => -20, 'value' => round($maxWait).'s on '.($maxWaitQueue ?? 'unknown')];
        } elseif ($maxWait !== null && $maxWait > 60) {
            $factors[] = ['key' => 'wait', 'impact' => -10, 'value' => round($maxWait).'s on '.($maxWaitQueue ?? 'unknown')];
        }

        if ($memoryPercentage !== null && $memoryPercentage > 90) {
            $factors[] = ['key' => 'memory', 'impact' => -30, 'value' => $memoryPercentage.'%'];
        } elseif ($memoryPercentage !== null && $memoryPercentage > 75) {
            $factors[] = ['key' => 'memory', 'impact' => -15, 'value' => $memoryPercentage.'%'];
        }

        return [
            'score'   => max(0, 100 + (int) collect($factors)->sum('impact')),
            'factors' => $factors,
        ];
    }
}
