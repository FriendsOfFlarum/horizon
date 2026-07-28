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

use Flarum\Testing\integration\ConsoleTestCase;
use FoF\Redis\Extend\Redis;
use FoF\Redis\Queue\RedisFailedJobProvider;
use Illuminate\Contracts\Redis\Factory;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * With horizon active, failed jobs are persisted by fof/redis's Redis-backed
 * failer — NOT the database. An operator running the Redis stack has moved
 * load off the database on purpose, and horizon already keeps its own failure
 * record in Redis, so core's queue.failer (which powers queue:failed /
 * queue:retry and core's dashboard) should also live in Redis rather than
 * horizon forcing it back to the queue_failed_jobs table.
 *
 * This pins the full round trip: a logged failure is stored in Redis (and the
 * database is NOT touched), and queue:retry pushes it back onto the redis
 * queue and clears the record.
 */
class FailedJobRoundTripTest extends ConsoleTestCase
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

    #[Test]
    public function the_failer_is_the_redis_failer_not_the_database_one()
    {
        $this->assertInstanceOf(
            RedisFailedJobProvider::class,
            $this->app()->getContainer()->make('queue.failer'),
            'horizon should defer to fof/redis\'s Redis failer, not override it with the database failer'
        );
    }

    #[Test]
    public function a_failed_job_is_persisted_to_redis_and_can_be_retried()
    {
        $container = $this->app()->getContainer();

        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = $container->make(Factory::class)->connection('default');
        $redis->flushdb();

        $db = $container->make('db.connection');
        $uuid = '0198c0de-0000-4000-8000-00000000f0f0';

        $payload = json_encode([
            'uuid'        => $uuid,
            'displayName' => 'FoF\\Horizon\\Tests\\WiringTestJob',
            'job'         => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'maxTries'    => null,
            'timeout'     => null,
            // queue:retry unserializes data.command to refresh attempts and
            // the retry_until timestamp, so it must be a serialized object.
            'data'        => ['commandName' => 'FoF\\Horizon\\Tests\\WiringTestJob', 'command' => serialize(new \stdClass())],
        ]);

        $failer = $container->make('queue.failer');
        $failer->log('redis', 'default', $payload, new RuntimeException('round trip test'));

        // Stored in Redis...
        $this->assertSame(1, $failer->count(), 'A logged failure must be recorded by the Redis failer.');
        $this->assertNotNull($failer->find($uuid), 'The failure must be findable by uuid.');

        // ...and NOT in the database.
        $this->assertSame(
            0,
            $db->table('queue_failed_jobs')->where('uuid', $uuid)->count(),
            'The Redis failer must not write to the queue_failed_jobs table.'
        );

        $this->runCommand(['command' => 'queue:retry', 'id' => [$uuid]]);

        // Retry removes it from the failer...
        $this->assertSame(0, $failer->count(), 'A retried job must be removed from the Redis failer.');
        $this->assertNull($failer->find($uuid));

        // ...and pushes it back onto the redis queue.
        $this->assertSame(
            1,
            (int) $redis->llen('queues:default'),
            'The retried job must be pushed back onto the redis queue.'
        );

        $redis->flushdb();
    }
}
