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

    private function memoryLimitFor(int $budget, string $current): ?string
    {
        return (new ReflectionMethod(WorkCommand::class, 'memoryLimitFor'))->invoke(null, $budget, $current);
    }

    /**
     * Horizon only restarts a worker over its `--memory` budget once a job has
     * finished. A job that crosses PHP's own memory_limit first dies with a
     * fatal error instead, so PHP's limit has to sit above the budget.
     */
    #[Test]
    public function the_php_limit_is_raised_to_twice_the_workers_budget()
    {
        // 96M: Debian's default CLI memory_limit, which killed workers with a
        // 128MB budget mid-job.
        $this->assertSame('256M', $this->memoryLimitFor(128, '96M'));
        $this->assertSame('1024M', $this->memoryLimitFor(512, '128M'));
    }

    #[Test]
    public function a_higher_php_limit_is_left_alone()
    {
        $this->assertNull($this->memoryLimitFor(128, '2G'));
        $this->assertNull($this->memoryLimitFor(128, '256M'));
    }

    #[Test]
    public function no_php_limit_is_left_alone()
    {
        $this->assertNull($this->memoryLimitFor(128, '-1'));
    }

    #[Test]
    public function a_worker_without_a_budget_changes_nothing()
    {
        $this->assertNull($this->memoryLimitFor(0, '96M'));
    }

    #[Test]
    public function the_worker_raises_its_own_limit_before_it_starts_working()
    {
        $command = new WorkCommand(
            $this->createStub(Worker::class),
            $this->createStub(Cache::class)
        );
        $command->setLaravel(new Container());

        (new ReflectionProperty($command, 'input'))->setValue($command, new ArrayInput(['--memory' => '128'], $command->getDefinition()));

        $original = ini_get('memory_limit');
        ini_set('memory_limit', '96M');

        try {
            (new ReflectionMethod($command, 'raiseMemoryLimit'))->invoke($command);

            $this->assertSame('256M', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $original);
        }
    }
}
