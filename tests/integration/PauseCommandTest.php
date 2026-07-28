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
use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use Laravel\Horizon\SupervisorCommands\Pause;
use PHPUnit\Framework\Attributes\Test;

/**
 * horizon:pause / horizon:continue must work across containers, for the same
 * reason as terminate: the stock Laravel commands send SIGUSR2/SIGCONT to
 * locally-discovered PIDs via posix_kill, which does nothing when the console
 * command runs in a different container than the master supervisor. They are
 * replaced with broadcasts through Horizon's redis command queue, which every
 * master polls each loop wherever it runs — the master is Pausable and
 * cascades pause()/continue() to its supervisors.
 */
class PauseCommandTest extends ConsoleTestCase
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

    private function seedRemoteMaster(): Factory
    {
        $container = $this->app()->getContainer();

        /** @var \Illuminate\Redis\Connections\Connection $redis */
        $redis = $container->make(Factory::class)->connection('horizon');
        $redis->flushdb();

        // A master supervisor "in another container": in the repository but
        // with a PID meaningless to this process.
        $redis->zadd('masters', ['other-container-AAAA' => time()]);
        $redis->hmset('master:other-container-AAAA', [
            'name'        => 'other-container-AAAA',
            'pid'         => 99999,
            'status'      => 'running',
            'supervisors' => '[]',
            'environment' => 'production',
        ]);

        return $container->make(Factory::class);
    }

    private function pendingCommands(): \Illuminate\Support\Collection
    {
        $queue = $this->app()->getContainer()->make(HorizonCommandQueue::class);

        return collect($queue->pending(MasterSupervisor::commandQueueFor('other-container-AAAA')));
    }

    #[Test]
    public function pause_is_broadcast_to_remote_masters_via_redis()
    {
        $this->seedRemoteMaster();

        $this->runCommand(['command' => 'horizon:pause']);

        $pending = $this->pendingCommands();

        $this->assertTrue(
            $pending->contains(fn ($command) => $command->command === Pause::class),
            'horizon:pause must push a Pause command onto every master supervisor\'s redis command queue.'
        );
    }

    #[Test]
    public function continue_is_broadcast_to_remote_masters_via_redis()
    {
        $this->seedRemoteMaster();

        $this->runCommand(['command' => 'horizon:continue']);

        $pending = $this->pendingCommands();

        $this->assertTrue(
            $pending->contains(fn ($command) => $command->command === ContinueWorking::class),
            'horizon:continue must push a ContinueWorking command onto every master supervisor\'s redis command queue.'
        );
    }
}
