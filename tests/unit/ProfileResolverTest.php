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

use FoF\Horizon\DefaultProfiles;
use FoF\Horizon\LayeredConfig;
use FoF\Horizon\ProfileResolver;
use FoF\Horizon\Supervisor;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ProfileResolverTest extends TestCase
{
    /**
     * A LayeredConfig stand-in backed by an in-memory map, keyed by the exact
     * env name the resolver asks for. Lets us drive scaling inputs without env
     * vars, config.php or settings.
     *
     * @param array<string, mixed> $values keyed by env var name
     */
    private function resolver(array $values = []): ProfileResolver
    {
        $config = new class($values) extends LayeredConfig {
            /** @param array<string, mixed> $values */
            public function __construct(private array $values)
            {
            }

            public function raw(string $envKey, ?string $configKey = null): mixed
            {
                return $this->values[$envKey] ?? null;
            }
        };

        return new ProfileResolver($config);
    }

    #[Test]
    public function standard_profile_resolves_base_times_multiplier()
    {
        $profile = DefaultProfiles::all()['standard']; // base 1 × 6 procs, mem 128 × 1

        $resolved = $this->resolver()->resolve($profile);

        $this->assertSame(6, $resolved['processes']);
        $this->assertSame(128, $resolved['memory']);
        $this->assertSame(60, $resolved['timeout']);
        $this->assertSame('redis', $resolved['connection']);
    }

    #[Test]
    public function pass_through_keys_survive_resolution()
    {
        // long is dormant by default (0 processes) and carries no queues; bring
        // it online with a queue so the resolver returns a supervisor whose
        // pass-through keys we can inspect.
        $long = DefaultProfiles::all()['long']->with(['queues' => ['exports']]);
        $resolved = $this->resolver(['REDIS_HORIZON_LONG_MAX_PROCESSES' => '1'])
            ->resolve($long);

        // long carries nice/balanceMaxShift/retry_after as arbitrary keys.
        $this->assertSame(10, $resolved['nice']);
        $this->assertSame(1800, $resolved['retry_after']);
        $this->assertSame('simple', $resolved['balance']);
    }

    #[Test]
    public function zero_process_profile_scales_to_zero_and_registers_nothing()
    {
        // fast/long/emails default to 0 base processes.
        $profile = DefaultProfiles::all()['fast'];

        $this->assertNull($this->resolver()->resolve($profile));
    }

    #[Test]
    public function literal_max_processes_env_wins_over_base_times_multiplier()
    {
        $profile = DefaultProfiles::all()['fast']->with(['queues' => ['realtime']]);

        $resolved = $this->resolver([
            'REDIS_HORIZON_FAST_MAX_PROCESSES' => '10',
        ])->resolve($profile);

        $this->assertSame(10, $resolved['processes']);
    }

    #[Test]
    public function a_tier_with_processes_but_no_queues_throws()
    {
        // fast defaults to no queues; requesting workers without routing a
        // queue is a misconfiguration (it would silently drain `default`).
        $profile = DefaultProfiles::all()['fast'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/no queues/');

        $this->resolver(['REDIS_HORIZON_FAST_MAX_PROCESSES' => '3'])->resolve($profile);
    }

    #[Test]
    public function processes_multiplier_env_scales_the_base()
    {
        $profile = new Supervisor(name: 'fast', queues: ['realtime'], processesBase: 2, processesMultiplier: 3);

        $resolved = $this->resolver([
            'REDIS_HORIZON_FAST_PROCESSES_MULTIPLIER' => '5',
        ])->resolve($profile);

        // base 2 × multiplier 5 = 10 (env multiplier overrides the profile's 3).
        $this->assertSame(10, $resolved['processes']);
    }

    #[Test]
    public function memory_limit_env_wins_and_lifts_the_supervisor_memory()
    {
        $profile = new Supervisor(name: 'long', queues: ['exports'], processesBase: 1, memoryBase: 128, memoryMultiplier: 4);

        $resolved = $this->resolver([
            'REDIS_HORIZON_LONG_MEMORY_LIMIT' => '512',
        ])->resolve($profile);

        $this->assertSame(512, $resolved['memory']);
    }

    #[Test]
    public function non_numeric_process_count_throws()
    {
        $profile = DefaultProfiles::all()['standard'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/REDIS_HORIZON_STANDARD_MAX_PROCESSES/');

        $this->resolver(['REDIS_HORIZON_STANDARD_MAX_PROCESSES' => 'lots'])->resolve($profile);
    }

    #[Test]
    public function negative_memory_throws()
    {
        $profile = DefaultProfiles::all()['standard'];

        $this->expectException(InvalidArgumentException::class);

        $this->resolver(['REDIS_HORIZON_STANDARD_MEMORY_LIMIT' => '-8'])->resolve($profile);
    }
}
