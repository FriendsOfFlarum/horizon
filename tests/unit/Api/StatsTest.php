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

namespace FoF\Horizon\Tests\unit\Api;

use FoF\Horizon\Api\Stats;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Redis\Connections\Connection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

/**
 * The health score counts memory pressure on the Redis server Horizon runs
 * on, read with one `INFO memory`: the dashboard polls this endpoint.
 */
class StatsTest extends TestCase
{
    /**
     * @param array|RuntimeException $reply what `INFO memory` returns, or throws
     */
    protected function memoryPercentage(array|RuntimeException $reply, ?string &$connectionName = null, ?array &$command = null): ?float
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('command')->willReturnCallback(function (string $method, array $parameters) use ($reply, &$command) {
            $command = [$method, $parameters];

            if ($reply instanceof RuntimeException) {
                throw $reply;
            }

            return $reply;
        });

        $redis = $this->createStub(RedisManager::class);
        $redis->method('connection')->willReturnCallback(function ($name) use ($connection, &$connectionName) {
            $connectionName = $name;

            return $connection;
        });

        $stats = (new ReflectionClass(Stats::class))->newInstanceWithoutConstructor();
        $stats->redis = $redis;

        return (new ReflectionMethod($stats, 'memoryPercentage'))->invoke($stats);
    }

    #[Test]
    public function it_asks_horizons_own_connection_for_the_memory_section_alone()
    {
        $this->memoryPercentage(['used_memory' => 50, 'maxmemory' => 200], $connectionName, $command);

        $this->assertSame('horizon', $connectionName);
        $this->assertSame(['info', ['memory']], $command);
    }

    #[Test]
    public function it_reports_memory_used_as_a_share_of_maxmemory()
    {
        $this->assertSame(25.0, $this->memoryPercentage(['used_memory' => 50, 'maxmemory' => 200]));
    }

    #[Test]
    public function it_unwraps_the_section_predis_nests_it_under()
    {
        $this->assertSame(25.0, $this->memoryPercentage(['Memory' => ['used_memory' => 50, 'maxmemory' => 200]]));
    }

    #[Test]
    public function there_is_no_share_without_a_maxmemory()
    {
        $this->assertNull($this->memoryPercentage(['used_memory' => 50, 'maxmemory' => 0]));
    }

    #[Test]
    public function a_failed_reading_leaves_memory_out_rather_than_failing_the_endpoint()
    {
        $this->assertNull($this->memoryPercentage(new RuntimeException('ERR unknown command')));
    }
}
