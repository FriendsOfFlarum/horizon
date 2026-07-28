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

namespace FoF\Horizon\Tests\integration;

use Flarum\Queue\QueueStatsProvider;
use Flarum\Testing\integration\TestCase;
use FoF\Horizon\Queue\HorizonQueueStatsProvider;
use FoF\Redis\Extend\Redis;
use PHPUnit\Framework\Attributes\Test;

/**
 * Horizon feeds core's queue dashboard through the same QueueStatsProvider
 * seam fof/redis uses, but enriches the payload with horizon-specific data
 * (worker processes, supervisors, throughput, wait time, paused status) that a
 * horizon-aware dashboard widget can render. The core-shape keys
 * (pending/reserved/failed) stay intact so core's own widget keeps working.
 */
class QueueStatsProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-horizon');

        $this->extend(
            (new Redis([
                'host'     => '127.0.0.1',
                'port'     => 6379,
                'database' => 13,
            ]))->useDatabaseWith('queue', 13)
        );
    }

    protected function stats(): QueueStatsProvider
    {
        return $this->app()->getContainer()->make(QueueStatsProvider::class);
    }

    #[Test]
    public function horizon_binds_the_enriched_stats_provider()
    {
        $this->assertInstanceOf(HorizonQueueStatsProvider::class, $this->stats());
    }

    #[Test]
    public function totals_keeps_the_core_shape()
    {
        $totals = $this->stats()->totals();

        // Core's widget reads exactly these three; they must remain present
        // and integer-typed regardless of the enrichment.
        $this->assertArrayHasKey('pending', $totals);
        $this->assertArrayHasKey('reserved', $totals);
        $this->assertArrayHasKey('failed', $totals);
        $this->assertIsInt($totals['pending']);
        $this->assertIsInt($totals['reserved']);
        $this->assertIsInt($totals['failed']);
    }

    #[Test]
    public function totals_carries_a_horizon_enrichment_block()
    {
        $horizon = $this->stats()->totals()['horizon'] ?? null;

        $this->assertIsArray($horizon, 'totals() must carry a horizon enrichment block');

        // The four enrichment fields the horizon-aware widget renders.
        $this->assertArrayHasKey('processes', $horizon);
        $this->assertArrayHasKey('supervisors', $horizon);
        $this->assertArrayHasKey('jobsPerMinute', $horizon);
        $this->assertArrayHasKey('maxWait', $horizon);
        $this->assertArrayHasKey('paused', $horizon);

        // Types the widget can rely on.
        $this->assertIsInt($horizon['processes']);
        $this->assertIsInt($horizon['supervisors']);
        $this->assertIsBool($horizon['paused']);
        // maxWait is a { queue, seconds } shape (or null when nothing is waiting).
        $this->assertTrue($horizon['maxWait'] === null || is_array($horizon['maxWait']));
    }

    #[Test]
    public function queues_keeps_the_core_shape()
    {
        $queues = $this->stats()->queues();

        // Default known-queues registry is ['default'].
        $this->assertArrayHasKey('default', $queues);
        $this->assertArrayHasKey('pending', $queues['default']);
        $this->assertArrayHasKey('reserved', $queues['default']);
    }
}
