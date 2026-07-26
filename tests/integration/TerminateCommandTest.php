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
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\Terminate;
use PHPUnit\Framework\Attributes\Test;

/**
 * horizon:terminate must work across containers/hosts: the stock Laravel
 * command inspects local processes and signals them by PID, which silently
 * does nothing when the console command runs in a different container than
 * the master supervisor (a standard Docker deployment). Instead, terminate
 * is broadcast through Horizon's redis command queue, which every master
 * polls on each loop regardless of where it runs.
 */
class TerminateCommandTest extends ConsoleTestCase
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
    public function terminate_is_broadcast_to_remote_masters_via_redis()
    {
        $container = $this->app()->getContainer();

        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = $container->make(Factory::class)->connection('horizon');
        $redis->flushdb();

        // A master supervisor running "in another container": present in the
        // repository (a sorted set scored by heartbeat timestamp), but its
        // PID is meaningless to this process.
        $redis->zadd('masters', ['other-container-AAAA' => time()]);
        $redis->hmset('master:other-container-AAAA', [
            'name'        => 'other-container-AAAA',
            'pid'         => 99999,
            'status'      => 'running',
            'supervisors' => '[]',
            'environment' => 'production',
        ]);

        $this->runCommand(['command' => 'horizon:terminate']);

        /** @var HorizonCommandQueue $queue */
        $queue = $container->make(HorizonCommandQueue::class);

        $pending = collect($queue->pending(MasterSupervisor::commandQueueFor('other-container-AAAA')));

        $redis->flushdb();

        $this->assertTrue(
            $pending->contains(fn ($command) => $command->command === Terminate::class),
            'horizon:terminate must push a Terminate command onto every master supervisor\'s redis command queue.'
        );
    }
}
