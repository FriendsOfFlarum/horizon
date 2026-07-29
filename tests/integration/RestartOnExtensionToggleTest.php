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

use Flarum\Extension\Event\Disabled;
use Flarum\Extension\Event\Enabled;
use Flarum\Extension\Extension;
use Flarum\Testing\integration\TestCase;
use FoF\Redis\Extend\Redis;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Redis\Factory;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\Terminate;
use PHPUnit\Framework\Attributes\Test;

/**
 * Enabling or disabling an extension can change the Horizon supervisor layout
 * (which queues exist, which profiles serve them). The master reads that layout
 * only at boot, so a toggle must restart the master or the change does not take
 * effect until a manual horizon:terminate. We restart it automatically by
 * broadcasting a terminate on the same Extension events core uses to restart the
 * queue workers.
 */
class RestartOnExtensionToggleTest extends TestCase
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

    private function seedRemoteMaster(): string
    {
        $redis = $this->app()->getContainer()->make(Factory::class)->connection('horizon');
        $redis->flushdb();

        $name = 'other-container-AAAA';
        $redis->zadd('masters', [$name => time()]);
        $redis->hmset('master:'.$name, [
            'name'        => $name,
            'pid'         => 99999,
            'status'      => 'running',
            'supervisors' => '[]',
            'environment' => 'production',
        ]);

        return $name;
    }

    private function terminateWasBroadcastTo(string $master): bool
    {
        /** @var HorizonCommandQueue $queue */
        $queue = $this->app()->getContainer()->make(HorizonCommandQueue::class);

        return collect($queue->pending(MasterSupervisor::commandQueueFor($master)))
            ->contains(fn ($command) => $command->command === Terminate::class);
    }

    #[Test]
    public function enabling_an_extension_restarts_the_master()
    {
        $master = $this->seedRemoteMaster();

        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(
            new Enabled(new Extension(__DIR__, ['name' => 'acme/example', 'type' => 'flarum-extension']))
        );

        $broadcast = $this->terminateWasBroadcastTo($master);

        $this->app()->getContainer()->make(Factory::class)->connection('horizon')->flushdb();

        $this->assertTrue($broadcast, 'Enabling an extension must broadcast a horizon terminate.');
    }

    #[Test]
    public function disabling_an_extension_restarts_the_master()
    {
        $master = $this->seedRemoteMaster();

        $this->app()->getContainer()->make(Dispatcher::class)->dispatch(
            new Disabled(new Extension(__DIR__, ['name' => 'acme/example', 'type' => 'flarum-extension']))
        );

        $broadcast = $this->terminateWasBroadcastTo($master);

        $this->app()->getContainer()->make(Factory::class)->connection('horizon')->flushdb();

        $this->assertTrue($broadcast, 'Disabling an extension must broadcast a horizon terminate.');
    }
}
