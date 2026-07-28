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

namespace FoF\Horizon\Tests\unit;

use FoF\Horizon\HorizonMetrics;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class HorizonMetricsTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function metrics(array $overrides = []): HorizonMetrics
    {
        return new HorizonMetrics(
            $overrides['supervisors'] ?? Mockery::mock(SupervisorRepository::class),
            $overrides['masters'] ?? Mockery::mock(MasterSupervisorRepository::class),
            $overrides['metrics'] ?? Mockery::mock(MetricsRepository::class),
            $overrides['waits'] ?? Mockery::mock(WaitTimeCalculator::class),
            $overrides['cache'] ?? Mockery::mock(CacheRepository::class),
            $overrides['queue'] ?? Mockery::mock(QueueFactory::class),
        );
    }

    #[Test]
    public function processes_sums_across_all_supervisors()
    {
        $supervisors = Mockery::mock(SupervisorRepository::class);
        $supervisors->shouldReceive('all')->andReturn([
            (object) ['processes' => ['queue-a' => 3, 'queue-b' => 2]],
            (object) ['processes' => ['queue-c' => 4]],
        ]);

        $this->assertSame(9, $this->metrics(['supervisors' => $supervisors])->processes());
    }

    #[Test]
    public function max_wait_is_null_when_nothing_is_waiting()
    {
        $waits = Mockery::mock(WaitTimeCalculator::class);
        $waits->shouldReceive('calculate')->andReturn([]);

        $this->assertNull($this->metrics(['waits' => $waits])->maxWait());
    }

    #[Test]
    public function max_wait_is_null_when_all_waits_are_zero()
    {
        $waits = Mockery::mock(WaitTimeCalculator::class);
        $waits->shouldReceive('calculate')->andReturn(['redis:default' => 0.0]);

        $this->assertNull($this->metrics(['waits' => $waits])->maxWait());
    }

    #[Test]
    public function max_wait_returns_the_slowest_queue_name_and_seconds()
    {
        // calculate() is keyed "connection:queue" and valued in MINUTES.
        $waits = Mockery::mock(WaitTimeCalculator::class);
        $waits->shouldReceive('calculate')->andReturn([
            'redis:default' => 0.5,
            'redis:emails'  => 2.0, // slowest
            'redis:low'     => 1.0,
        ]);

        $result = $this->metrics(['waits' => $waits])->maxWait();

        $this->assertSame('emails', $result['queue'], 'the connection prefix must be stripped');
        $this->assertSame(120, $result['seconds'], '2.0 minutes must convert to 120 seconds');
    }

    #[Test]
    public function queue_paused_in_core_is_true_on_a_wildcard_pause()
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('illuminate:queue:paused:redis:*', false)->andReturn(true);

        $this->assertTrue($this->pausedMetrics($cache)->queuePausedInCore(['default']));
    }

    #[Test]
    public function queue_paused_in_core_is_true_when_a_named_queue_is_paused()
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->with('illuminate:queue:paused:redis:*', false)->andReturn(false);
        $cache->shouldReceive('get')->with('illuminate:queue:paused:redis:default', false)->andReturn(false);
        $cache->shouldReceive('get')->with('illuminate:queue:paused:redis:emails', false)->andReturn(true);

        $this->assertTrue($this->pausedMetrics($cache)->queuePausedInCore(['default', 'emails']));
    }

    #[Test]
    public function queue_paused_in_core_is_false_when_nothing_is_paused()
    {
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('get')->andReturn(false);

        $this->assertFalse($this->pausedMetrics($cache)->queuePausedInCore(['default']));
    }

    /**
     * Build a metrics instance whose queue factory reports the 'redis'
     * connection name, for the pause-key tests.
     */
    private function pausedMetrics(CacheRepository $cache): HorizonMetrics
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('getConnectionName')->andReturn('redis');

        $queue = Mockery::mock(QueueFactory::class);
        $queue->shouldReceive('connection')->andReturn($connection);

        return $this->metrics(['cache' => $cache, 'queue' => $queue]);
    }
}
