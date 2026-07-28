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

use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use FoF\Redis\Extend\Redis;
use PHPUnit\Framework\Attributes\Test;

/**
 * The dashboard stats endpoint must only report metrics that are honest:
 *
 * - Job counts are returned raw, with their periods (the trim windows), so
 *   the UI can label them correctly. The old payload computed a "failure
 *   rate" by dividing the failed count (7-day window by default) by the
 *   recent count (1-hour window) — producing meaningless percentages.
 * - The health score is derived from same-moment observations only, and
 *   returns the factors that produced it so the UI can explain itself.
 * - Redis server info is namespaced separately and carries an explicit
 *   warning flag when the eviction policy can evict queue data.
 */
class StatsEndpointTest extends TestCase
{
    use RetrievesAuthorizedUsers;

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

        $this->prepareDatabase([
            \Flarum\User\User::class => [$this->normalUser()],
        ]);
    }

    private function stats(): array
    {
        $response = $this->send(
            $this->request('GET', '/admin/horizon/api/stats', ['authenticatedAs' => 1])
        );

        $this->assertEquals(200, $response->getStatusCode());

        return json_decode($response->getBody()->getContents(), true);
    }

    #[Test]
    public function cross_window_rates_are_not_reported()
    {
        $stats = $this->stats();

        $this->assertArrayNotHasKey('successRate', $stats);
        $this->assertArrayNotHasKey('failureRate', $stats);
    }

    #[Test]
    public function a_core_paused_queue_reports_the_status_as_paused()
    {
        // No master runs in the test environment (status would be 'inactive'),
        // but core's queue pause is a separate mechanism: with the queue paused
        // via the shared cache flag, no jobs are being processed, so the
        // dashboard must report a single unified "paused" status rather than
        // leaving the operator to reconcile two different pause signals.
        $cache = $this->app()->getContainer()->make('cache.store');
        $cache->forever('illuminate:queue:paused:redis:*', true);

        try {
            $this->assertSame('paused', $this->stats()['status']);
        } finally {
            $cache->forget('illuminate:queue:paused:redis:*');
        }
    }

    #[Test]
    public function a_core_paused_individual_queue_also_reports_paused()
    {
        $cache = $this->app()->getContainer()->make('cache.store');
        $cache->forever('illuminate:queue:paused:redis:default', true);

        try {
            $this->assertSame('paused', $this->stats()['status']);
        } finally {
            $cache->forget('illuminate:queue:paused:redis:default');
        }
    }

    #[Test]
    public function job_counts_come_with_their_periods()
    {
        $stats = $this->stats();

        $this->assertArrayHasKey('recentJobs', $stats);
        $this->assertArrayHasKey('failedJobs', $stats);
        $this->assertArrayHasKey('pendingJobs', $stats);

        // Trim defaults: recent 60 minutes, recent_failed 10080 minutes. The
        // UI must label the counts with these windows instead of guessing.
        $this->assertEquals(60, $stats['periods']['recentJobs']);
        $this->assertEquals(10080, $stats['periods']['failedJobs']);
    }

    #[Test]
    public function health_is_a_score_with_explanatory_factors()
    {
        $stats = $this->stats();

        $health = $stats['health'];

        $this->assertIsInt($health['score']);

        // No master supervisor runs in the test environment, so the status
        // factor must report the inactive deduction.
        $this->assertSame('inactive', $stats['status']);

        $factors = collect($health['factors']);
        $statusFactor = $factors->firstWhere('key', 'status');

        $this->assertNotNull($statusFactor, 'The status factor must be present when horizon is inactive.');
        $this->assertLessThan(0, $statusFactor['impact']);
        $this->assertSame(100 + collect($health['factors'])->sum('impact'), $health['score']);
    }

    #[Test]
    public function redis_info_is_namespaced_and_reports_the_eviction_policy()
    {
        $stats = $this->stats();

        $redis = $stats['redis'];

        $this->assertArrayHasKey('memory_used', $redis);
        $this->assertArrayHasKey('memory_percentage', $redis);
        $this->assertArrayHasKey('ops_per_sec', $redis);

        // The policy is informational: eviction policies like allkeys-lru
        // are perfectly reasonable for a Flarum cache store. The UI only
        // warns when the policy can evict AND memory pressure is real.
        $this->assertNotSame('', $redis['eviction_policy']);
    }

    #[Test]
    public function queue_extremes_are_labeled_as_queue_names()
    {
        $stats = $this->stats();

        // These are queue NAMES (possibly null when no metrics exist yet) —
        // the old payload rendered them as if they were durations/rates.
        $this->assertArrayHasKey('busiestQueues', $stats);
        $this->assertArrayHasKey('slowestQueue', $stats['busiestQueues']);
        $this->assertArrayHasKey('highestThroughputQueue', $stats['busiestQueues']);
    }
}
