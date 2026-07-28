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

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;

/**
 * Shared Horizon queue-health metrics.
 *
 * Single source of truth for "how is the queue doing" figures — process count,
 * supervisor count, throughput, longest wait, and a unified paused flag. Both
 * the Api\Stats dashboard endpoint and the core-dashboard QueueStatsProvider
 * consume this, so the two can never drift apart.
 */
class HorizonMetrics
{
    public function __construct(
        protected SupervisorRepository $supervisors,
        protected MasterSupervisorRepository $masters,
        protected MetricsRepository $metrics,
        protected WaitTimeCalculator $waits,
        protected CacheRepository $cache,
        protected QueueFactory $queue
    ) {
    }

    /**
     * Total worker processes across all supervisors.
     */
    public function processes(): int
    {
        /** @var \Illuminate\Support\Collection<int, object> $supervisors */
        $supervisors = collect($this->supervisors->all());

        return $supervisors->reduce(function (int $carry, $supervisor): int {
            /** @var array<int, int> $processes */
            $processes = $supervisor->processes;

            return $carry + (int) collect($processes)->sum();
        }, 0);
    }

    /**
     * Number of registered supervisors.
     */
    public function supervisorCount(): int
    {
        return count($this->supervisors->all());
    }

    /**
     * Jobs processed per minute (Horizon's rolling throughput metric).
     */
    public function jobsPerMinute(): int
    {
        return (int) $this->metrics->jobsProcessedPerMinute();
    }

    /**
     * The longest current wait across queues as { queue, seconds }, or null
     * when nothing is waiting.
     *
     * @return array{queue: string, seconds: int}|null
     */
    public function maxWait(): ?array
    {
        $waits = collect($this->waits->calculate());

        if ($waits->isEmpty()) {
            return null;
        }

        $maxMinutes = (float) $waits->max();

        if ($maxMinutes <= 0) {
            return null;
        }

        // calculate() is keyed by "connection:queue"; surface the queue name.
        $key = (string) $waits->search($maxMinutes);
        $queue = str_contains($key, ':') ? substr($key, strpos($key, ':') + 1) : $key;

        return [
            'queue'   => $queue,
            'seconds' => (int) round($maxMinutes * 60),
        ];
    }

    /**
     * A single unified paused flag: true when either Horizon's master is
     * paused OR the queue is paused through core's queue-pause mechanism. Both
     * mean the same thing to an operator — jobs aren't flowing.
     */
    public function paused(): bool
    {
        if ($this->queuePausedInCore()) {
            return true;
        }

        $masters = $this->masters->all();

        if (empty($masters)) {
            return false;
        }

        return collect($masters)->contains(fn ($master) => $master->status === 'paused');
    }

    /**
     * Whether the queue is paused through core's queue-pause mechanism, read
     * from the shared cache using Illuminate's own key format (a `*` wildcard
     * pause covers every queue; otherwise any known queue being paused counts).
     *
     * @param list<string> $queues the known queue names to check
     */
    public function queuePausedInCore(array $queues = ['default']): bool
    {
        $connection = $this->queue->connection()->getConnectionName() ?? 'redis';

        if ($this->cache->get("illuminate:queue:paused:{$connection}:*", false)) {
            return true;
        }

        foreach ($queues as $queue) {
            if ($this->cache->get("illuminate:queue:paused:{$connection}:{$queue}", false)) {
                return true;
            }
        }

        return false;
    }
}
