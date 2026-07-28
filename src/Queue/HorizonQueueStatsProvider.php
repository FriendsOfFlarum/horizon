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

namespace FoF\Horizon\Queue;

use FoF\Horizon\HorizonMetrics;
use FoF\Redis\Queue\RedisQueueStatsProvider;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * Feeds core's queue dashboard when Horizon drives the queue.
 *
 * Extends fof/redis's provider so the core-shape counts (pending / reserved /
 * failed, plus per-queue) are exactly what core's own QueueWidget renders, and
 * enriches totals() with a `horizon` block — worker processes, supervisor
 * count, throughput, the longest current wait, and a unified paused flag —
 * that a Horizon-aware dashboard widget can display. Core's widget ignores the
 * extra key; only the Horizon subclass reads it.
 *
 * The enrichment figures come from the shared {@see HorizonMetrics} service,
 * the same source Horizon's own Api\Stats endpoint uses, so the two dashboards
 * can never disagree.
 */
class HorizonQueueStatsProvider extends RedisQueueStatsProvider
{
    public function __construct(
        Queue $queue,
        FailedJobProviderInterface $failer,
        array $queues,
        protected HorizonMetrics $metrics
    ) {
        parent::__construct($queue, $failer, $queues);
    }

    public function totals(): array
    {
        return array_merge(parent::totals(), [
            'horizon' => [
                'processes'     => $this->metrics->processes(),
                'supervisors'   => $this->metrics->supervisorCount(),
                'jobsPerMinute' => $this->metrics->jobsPerMinute(),
                'maxWait'       => $this->metrics->maxWait(),
                'paused'        => $this->metrics->paused(),
            ],
        ]);
    }
}
