<?php

/*
 * This file is part of fof/horizon.
 *
 * Copyright (c) FriendsOfFlarum
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon\Tests\unit;

use FoF\Horizon\HealthScore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HealthScoreTest extends TestCase
{
    private function score(string $status = 'running', ?float $maxWait = null, ?string $queue = null, ?float $memory = null): array
    {
        return (new HealthScore())->calculate($status, $maxWait, $queue, $memory);
    }

    #[Test]
    public function healthy_system_scores_100_with_no_factors()
    {
        $health = $this->score();

        $this->assertSame(100, $health['score']);
        $this->assertSame([], $health['factors']);
    }

    #[Test]
    public function inactive_costs_50()
    {
        $health = $this->score(status: 'inactive');

        $this->assertSame(50, $health['score']);
        $this->assertSame(-50, $health['factors'][0]['impact']);
        $this->assertSame('status', $health['factors'][0]['key']);
    }

    #[Test]
    public function paused_costs_20()
    {
        $health = $this->score(status: 'paused');

        $this->assertSame(80, $health['score']);
        $this->assertSame('paused', $health['factors'][0]['value']);
    }

    #[Test]
    public function long_wait_costs_20_and_names_the_queue()
    {
        $health = $this->score(maxWait: 6.2, queue: 'media');

        $this->assertSame(80, $health['score']);
        $this->assertSame('wait', $health['factors'][0]['key']);
        $this->assertStringContainsString('media', $health['factors'][0]['value']);
    }

    #[Test]
    public function moderate_wait_costs_10()
    {
        $this->assertSame(90, $this->score(maxWait: 2.0)['score']);
    }

    #[Test]
    public function short_wait_is_not_a_factor()
    {
        $this->assertSame(100, $this->score(maxWait: 0.5)['score']);
    }

    #[Test]
    public function memory_pressure_costs_15_over_75_percent_and_30_over_90()
    {
        $this->assertSame(85, $this->score(memory: 80.0)['score']);
        $this->assertSame(70, $this->score(memory: 95.0)['score']);
    }

    #[Test]
    public function unknown_memory_is_not_a_factor()
    {
        $this->assertSame(100, $this->score(memory: null)['score']);
    }

    #[Test]
    public function worst_case_bottoms_out_at_zero_and_score_always_equals_100_plus_impacts()
    {
        $health = $this->score(status: 'inactive', maxWait: 10.0, queue: 'default', memory: 95.0);

        $this->assertSame(0, $health['score']);
        $this->assertSame(100 + array_sum(array_column($health['factors'], 'impact')), $health['score']);
    }
}
