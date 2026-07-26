<?php

/*
 * This file is part of fof/horizon.
 *
 * Copyright (c) FriendsOfFlarum
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon\Tests\unit\Console;

use Flarum\Foundation\Config;
use FoF\Horizon\Console\WorkCommand;
use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Queue\Worker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ReflectionProperty;
use Symfony\Component\Console\Input\ArrayInput;

/**
 * Horizon workers must follow core's queue worker semantics for maintenance
 * modes (introduced with advanced maintenance modes): the queue keeps running
 * in low and safe maintenance, and only pauses in high maintenance.
 */
class WorkCommandTest extends TestCase
{
    private function downForMaintenance(mixed $offline, bool $force = false): bool
    {
        $command = new WorkCommand(
            $this->createStub(Worker::class),
            $this->createStub(Cache::class)
        );

        $container = new Container();
        $container->instance(Config::class, new Config([
            'url'     => 'http://flarum.test',
            'offline' => $offline,
        ]));
        $command->setLaravel($container);

        $input = new ArrayInput(
            $force ? ['--force' => true] : [],
            $command->getDefinition()
        );

        $inputProperty = new ReflectionProperty($command, 'input');
        $inputProperty->setValue($command, $input);

        $method = new ReflectionMethod($command, 'downForMaintenance');

        return $method->invoke($command);
    }

    #[Test]
    public function pauses_in_high_maintenance_mode()
    {
        $this->assertTrue($this->downForMaintenance('high'));
        $this->assertTrue($this->downForMaintenance(true));
    }

    #[Test]
    public function keeps_working_in_low_maintenance_mode()
    {
        $this->assertFalse($this->downForMaintenance('low'));
    }

    #[Test]
    public function keeps_working_in_safe_mode()
    {
        $this->assertFalse($this->downForMaintenance('safe'));
    }

    #[Test]
    public function keeps_working_when_not_in_maintenance()
    {
        $this->assertFalse($this->downForMaintenance(false));
    }

    #[Test]
    public function force_overrides_high_maintenance()
    {
        $this->assertFalse($this->downForMaintenance('high', force: true));
    }
}
