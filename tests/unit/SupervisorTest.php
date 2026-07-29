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

namespace FoF\Horizon\Tests\unit;

use FoF\Horizon\Supervisor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SupervisorTest extends TestCase
{
    #[Test]
    public function with_maps_recognised_keys_onto_typed_properties()
    {
        $s = (new Supervisor(name: 'fast'))->with([
            'queues'  => ['realtime', 'iplookup'],
            'tries'   => 5,
            'timeout' => 9,
            'balance' => 'simple',
        ]);

        $this->assertSame(['realtime', 'iplookup'], $s->queues);
        $this->assertSame(5, $s->tries);
        $this->assertSame(9, $s->timeout);
        $this->assertSame('simple', $s->balance);
    }

    #[Test]
    public function literal_processes_pins_the_base_and_neutralises_the_multiplier()
    {
        $s = (new Supervisor(name: 'fast', processesBase: 1, processesMultiplier: 12))
            ->with(['processes' => 8]);

        $this->assertSame(8, $s->processesBase);
        $this->assertSame(1, $s->processesMultiplier);
    }

    #[Test]
    public function base_and_multiplier_can_be_set_independently()
    {
        $s = (new Supervisor(name: 'fast'))->with([
            'processesBase'       => 2,
            'processesMultiplier' => 4,
            'memoryBase'          => 256,
            'memoryMultiplier'    => 2,
        ]);

        $this->assertSame(2, $s->processesBase);
        $this->assertSame(4, $s->processesMultiplier);
        $this->assertSame(256, $s->memoryBase);
        $this->assertSame(2, $s->memoryMultiplier);
    }

    #[Test]
    public function unknown_keys_pass_through_to_overrides()
    {
        $s = (new Supervisor(name: 'long'))->with([
            'nice'            => 10,
            'retry_after'     => 1800,
            'balanceMaxShift' => 1,
        ]);

        $this->assertSame(10, $s->overrides['nice']);
        $this->assertSame(1800, $s->overrides['retry_after']);
        $this->assertSame(1, $s->overrides['balanceMaxShift']);
    }

    #[Test]
    public function with_returns_a_new_instance_leaving_the_original_untouched()
    {
        $original = new Supervisor(name: 'standard', tries: 1);
        $modified = $original->with(['tries' => 9]);

        $this->assertSame(1, $original->tries);
        $this->assertSame(9, $modified->tries);
    }

    #[Test]
    public function comma_separated_queue_string_is_normalised_to_a_list()
    {
        $s = (new Supervisor(name: 'fast'))->with(['queues' => 'realtime, iplookup ,gdpr']);

        $this->assertSame(['realtime', 'iplookup', 'gdpr'], $s->queues);
    }
}
