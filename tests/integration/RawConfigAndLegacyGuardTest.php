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
use FoF\Horizon\Extend\Horizon;
use FoF\Redis\Extend\Redis;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\Test;

/**
 * The supervisor-profile model owns the Horizon `environments` layout, so a
 * hand-written one — the pre-profile way of configuring workers — is no longer
 * silently applied. Passing it through the legacy ->config()/->environment()
 * escape hatches, or config.php's horizon.environments, throws at boot with a
 * pointer to the supported paths. The sanctioned way to take full manual
 * control is useRawConfig(), which bypasses the profile system entirely.
 */
class RawConfigAndLegacyGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('fof-horizon');

        $this->extend(
            (new Redis([
                'host' => '127.0.0.1',
                'port' => 6379,
            ]))->useDatabaseWith('queue', 13)
        );
    }

    private function environments(): array
    {
        $container = $this->app()->getContainer();
        $config = $container->make(Repository::class)->get('horizon', []);
        $env = $container->make('env');

        return $config['environments'][$env] ?? [];
    }

    #[Test]
    public function use_raw_config_replaces_the_profile_layout_verbatim()
    {
        // A supervisor map for the current environment — fof/horizon keys it
        // under the running env, so no need to know whether that's
        // production/testing/etc.
        $this->extend(
            (new Horizon())->useRawConfig([
                'supervisor-custom' => [
                    'connection'   => 'redis',
                    'queue'        => ['default', 'bespoke'],
                    'balance'      => 'simple',
                    'maxProcesses' => 3,
                ],
            ])
        );

        $supervisors = $this->environments();

        // Only the raw supervisor exists — the built-in profiles (standard,
        // emails, …) are bypassed entirely.
        $this->assertSame(['supervisor-custom'], array_keys($supervisors));
        $this->assertSame(['default', 'bespoke'], $supervisors['supervisor-custom']['queue']);
    }

    /**
     * Assert booting the app throws an InvalidArgumentException whose message
     * matches, catching it manually. The guard fires during boot, and a plain
     * expectException would let the same exception re-throw from tearDown's
     * app() call and error the test — so we catch it here and leave state clean.
     */
    private function assertBootThrows(?string $messageContains = null): void
    {
        try {
            $this->app();
            $this->fail('Expected boot to throw an InvalidArgumentException.');
        } catch (\InvalidArgumentException $e) {
            if ($messageContains !== null) {
                $this->assertStringContainsString($messageContains, $e->getMessage());
            } else {
                $this->assertTrue(true);
            }
        } finally {
            // The failed boot never assigned $this->app, so tearDown() would
            // boot again and re-throw. Clear the offending extenders/config so
            // the harness can tear down cleanly.
            $this->extenders = [];
            unset($this->config['horizon']);
        }
    }

    #[Test]
    public function legacy_config_with_environments_throws()
    {
        $this->extend(
            (new Horizon())->config([
                'environments' => ['production' => ['supervisor-1' => ['maxProcesses' => 5]]],
            ])
        );

        $this->assertBootThrows('useRawConfig');
    }

    #[Test]
    public function legacy_environment_method_throws()
    {
        $this->extend(
            (new Horizon())->environment(['supervisor-1' => ['maxProcesses' => 5]])
        );

        $this->assertBootThrows();
    }

    #[Test]
    public function config_php_environments_throws()
    {
        $this->config('horizon', [
            'environments' => ['production' => ['supervisor-1' => ['maxProcesses' => 5]]],
        ]);

        $this->assertBootThrows('useRawConfig');
    }

    #[Test]
    public function config_php_supervisors_is_the_supported_surface_and_does_not_throw()
    {
        // horizon.supervisors is how you configure profiles via config.php — it
        // must NOT trip the legacy guard.
        $this->config('horizon', [
            'supervisors' => ['fast' => ['queues' => ['realtime'], 'processes' => 2]],
        ]);

        $this->assertContains('supervisor-fast', array_keys($this->environments()));
    }

    #[Test]
    public function a_plain_config_call_without_environments_still_works()
    {
        // ->config() remains valid for other top-level Horizon keys.
        $this->extend((new Horizon())->config(['fast_termination' => true]));

        $config = $this->app()->getContainer()->make(Repository::class)->get('horizon', []);

        $this->assertTrue($config['fast_termination']);
        // Profiles still assembled normally.
        $this->assertContains('supervisor-standard', array_keys($this->environments()));
    }
}
