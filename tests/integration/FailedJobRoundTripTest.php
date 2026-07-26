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
use Illuminate\Contracts\Redis\Factory;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

/**
 * With horizon active, core's queue.failer used to be the Null provider —
 * failed jobs were silently discarded and queue:retry/queue:failed were
 * no-ops. This pins the full round trip: a logged failure is persisted to
 * queue_failed_jobs, and queue:retry pushes it back onto the redis queue
 * and clears the record.
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
    public function a_failed_job_is_persisted_and_can_be_retried()
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

        $this->assertSame(
            1,
            $db->table('queue_failed_jobs')->where('uuid', $uuid)->count(),
            'A logged failure must be persisted to queue_failed_jobs.'
        );

        $this->runCommand(['command' => 'queue:retry', 'id' => [$uuid]]);

        $this->assertSame(
            0,
            $db->table('queue_failed_jobs')->where('uuid', $uuid)->count(),
            'A retried job must be removed from queue_failed_jobs.'
        );

        $this->assertSame(
            1,
            (int) $redis->llen('queues:default'),
            'The retried job must be pushed back onto the redis queue.'
        );

        $redis->flushdb();
    }
}
