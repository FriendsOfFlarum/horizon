<?php

/*
 * This file is part of fof/horizon.
 *
 * Copyright (c) FriendsOfFlarum
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon\Tests\unit\Extend;

use FoF\Horizon\Extend\Queue;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as RepositoryContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class QueueTest extends TestCase
{
    private function container(array $initialConfig = []): Container
    {
        $container = new Container();
        $container->instance(RepositoryContract::class, new Repository($initialConfig));

        return $container;
    }

    private function config(Container $container): RepositoryContract
    {
        return $container->make(RepositoryContract::class);
    }

    #[Test]
    public function config_file_is_applied_when_no_queue_config_exists()
    {
        $container = $this->container();

        (new Queue())
            ->config(__DIR__.'/../../fixtures/queue.php')
            ->extend($container);

        $this->assertSame('redis', $this->config($container)->get('queue.default'));
        $this->assertSame('redis', $this->config($container)->get('queue.connections.redis.driver'));
    }

    #[Test]
    public function config_file_keys_survive_when_queue_config_already_exists()
    {
        // Simulates another extender (or core) having set queue config first.
        $container = $this->container([
            'queue' => ['default' => 'existing-driver'],
        ]);

        (new Queue())
            ->config(__DIR__.'/../../fixtures/queue.php')
            ->extend($container);

        $config = $this->config($container);

        // Documented precedence: existing keys win over the config file...
        $this->assertSame('existing-driver', $config->get('queue.default'));

        // ...but keys only present in the file must not be silently discarded.
        $this->assertSame('redis', $config->get('queue.connections.redis.driver'));
    }

    #[Test]
    public function added_connections_are_registered()
    {
        $container = $this->container();

        (new Queue())
            ->addConnection('custom', ['driver' => 'redis', 'queue' => 'high'])
            ->extend($container);

        $this->assertSame('high', $this->config($container)->get('queue.connections.custom.queue'));
    }
}
