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

use Flarum\Testing\integration\TestCase;
use FoF\Horizon\Overrides\RedisQueue;
use FoF\Redis\Extend\Redis;
use FoF\Redis\Queue\RedisFailedJobProvider;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\Test;

class HorizonConfigurationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-horizon');

        // Wire redis the way a site does in its local extend.php. Database 13
        // keeps test traffic away from any real data on the shared instance.
        $this->extend(
            (new Redis([
                'host' => '127.0.0.1',
                'port' => 6379,
            ]))->useDatabaseWith('queue', 13)
        );
    }

    private function horizonConfig(): array
    {
        $container = $this->app()->getContainer();

        return $container->make(Repository::class)->get('horizon', []);
    }

    private function supervisorConfig(): array
    {
        $config = $this->horizonConfig();
        $env = $this->app()->getContainer()->make('env');

        return $config['environments'][$env]['supervisor-1'] ?? [];
    }

    /**
     * illuminate/queue 13.15.0 type-hinted WorkerIdle::$connectionName as
     * string, so a connection without a name crashes the worker loop.
     * Mirrors flarum/framework#4700; fof/horizon issue #25.
     */
    #[Test]
    public function queue_connection_is_a_named_horizon_redis_queue()
    {
        $queue = $this->app()->getContainer()->make('flarum.queue.connection');

        $this->assertInstanceOf(RedisQueue::class, $queue);
        $this->assertSame('redis', $queue->getConnectionName());
    }

    #[Test]
    public function supervisor_defaults_match_the_documented_baseline()
    {
        $supervisor = $this->supervisorConfig();

        $this->assertSame(['default'], $supervisor['queue']);
        $this->assertSame('auto', $supervisor['balance']);
        $this->assertSame(4, $supervisor['processes']);
        $this->assertSame(3, $supervisor['tries']);
        $this->assertSame(128, $supervisor['memory']);
    }

    #[Test]
    public function supervisor_is_configurable_via_admin_settings()
    {
        $this->setting('fof-horizon.supervisor.processes', '7');
        $this->setting('fof-horizon.supervisor.memory', '256');
        $this->setting('fof-horizon.supervisor.tries', '5');
        $this->setting('fof-horizon.supervisor.queues', 'default,media');
        $this->setting('fof-horizon.supervisor.balance', 'simple');

        $supervisor = $this->supervisorConfig();

        $this->assertSame(['default', 'media'], $supervisor['queue']);
        $this->assertSame('simple', $supervisor['balance']);
        $this->assertSame(7, $supervisor['processes']);
        $this->assertSame(5, $supervisor['tries']);
        $this->assertSame(256, $supervisor['memory']);
    }

    #[Test]
    public function config_php_overrides_admin_settings()
    {
        $this->setting('fof-horizon.supervisor.processes', '7');

        $this->config('horizon', [
            'supervisor' => ['processes' => 9],
        ]);

        $this->assertSame(9, $this->supervisorConfig()['processes']);
    }

    #[Test]
    public function environment_variables_override_everything()
    {
        $this->setting('fof-horizon.supervisor.processes', '7');

        $this->config('horizon', [
            'supervisor' => ['processes' => 9],
        ]);

        putenv('FOF_HORIZON_PROCESSES=11');
        putenv('FOF_HORIZON_QUEUES=default,critical');

        try {
            $supervisor = $this->supervisorConfig();

            $this->assertSame(11, $supervisor['processes']);
            $this->assertSame(['default', 'critical'], $supervisor['queue']);
        } finally {
            putenv('FOF_HORIZON_PROCESSES');
            putenv('FOF_HORIZON_QUEUES');
        }
    }

    #[Test]
    public function trim_settings_follow_the_same_layering()
    {
        $this->setting('fof-horizon.trim.recent', '90');

        putenv('FOF_HORIZON_TRIM_COMPLETED=120');

        try {
            $config = $this->horizonConfig();

            // Settings UI value.
            $this->assertSame(90, (int) $config['trim']['recent']);
            // Env override.
            $this->assertSame(120, (int) $config['trim']['completed']);
            // Untouched default.
            $this->assertSame(60, (int) $config['trim']['pending']);
        } finally {
            putenv('FOF_HORIZON_TRIM_COMPLETED');
        }
    }

    /**
     * Core registers queue:retry / queue:failed CLI commands whenever the
     * connection is not sync — with a Null failer they are silent no-ops.
     * With horizon active, failed jobs must be persisted. fof/horizon defers
     * to fof/redis's Redis-backed failer (rather than forcing the database
     * failer), keeping failures in Redis alongside the rest of the stack.
     */
    #[Test]
    public function failed_jobs_are_persisted_not_discarded()
    {
        $failer = $this->app()->getContainer()->make('queue.failer');

        $this->assertInstanceOf(RedisFailedJobProvider::class, $failer);
    }
}
