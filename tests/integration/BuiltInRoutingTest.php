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

use Flarum\Gdpr\Jobs\GdprJob;
use Flarum\Mail\Job\SendInformationalEmailJob;
use Flarum\Notification\Job\SendEmailNotificationJob;
use Flarum\Realtime\Push\Jobs\Job as RealtimeJob;
use Flarum\Testing\integration\TestCase;
use FoF\GeoIP\Jobs\RetrieveIP;
use FoF\Redis\Extend\Redis;
use Illuminate\Contracts\Config\Repository;
use PHPUnit\Framework\Attributes\Test;

/**
 * fof/horizon wires Flarum's own queued work onto the built-in profiles with no
 * configuration: core mail jobs onto the always-on `emails` profile's `mail`
 * queue, and — when the relevant extension is enabled — realtime jobs onto
 * `fast`/`realtime` and gdpr jobs onto `long`/`gdpr`, bringing those tiers
 * online.
 *
 * These use the real flarum/realtime and flarum/gdpr packages (dev deps) so the
 * routed job classes, core's queue-route resolution and the enabled-checks are
 * all exercised for real.
 */
class BuiltInRoutingTest extends TestCase
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

    private function supervisor(string $name): array
    {
        $container = $this->app()->getContainer();
        $config = $container->make(Repository::class)->get('horizon', []);
        $env = $container->make('env');

        return $config['environments'][$env]['supervisor-'.$name] ?? [];
    }

    private function knownQueues(): array
    {
        return $this->app()->getContainer()->make('flarum.queue.queues');
    }

    /**
     * The queue a job class is routed to, resolved through core's route map by
     * class name (covering hierarchy: an abstract base route covers subclasses).
     */
    private function routeFor(string $jobClass): ?string
    {
        $routes = $this->app()->getContainer()->make('queue.routes')->all();

        // Map entries are [connection, queue] or a bare queue string.
        $route = $routes[$jobClass] ?? null;

        return is_array($route) ? ($route[1] ?? null) : $route;
    }

    #[Test]
    public function core_mail_jobs_are_routed_onto_the_mail_queue()
    {
        $this->app();

        $this->assertSame('mail', $this->routeFor(SendEmailNotificationJob::class));
        $this->assertSame('mail', $this->routeFor(SendInformationalEmailJob::class));
    }

    #[Test]
    public function realtime_stays_dormant_when_the_extension_is_disabled()
    {
        $this->app();

        // The realtime tier has no queues / no supervisor until realtime is enabled.
        $this->assertSame([], $this->supervisor('realtime'));
        $this->assertNotContains('realtime', $this->knownQueues());
    }

    #[Test]
    public function enabling_realtime_brings_its_own_tier_online_and_routes_realtime_jobs()
    {
        $this->extension('flarum-realtime');
        $this->app();

        $realtime = $this->supervisor('realtime');

        $this->assertNotEmpty($realtime, 'the realtime tier must register once realtime is enabled');
        $this->assertContains('realtime', $realtime['queue']);
        $this->assertSame('realtime', $this->routeFor(RealtimeJob::class));
        $this->assertContains('realtime', $this->knownQueues());
    }

    /**
     * Realtime pushes reach our own websocket server, which restarts on every
     * deployment — and a deployment is when the asset-revision broadcast fires.
     * A push caught in that window fails in transit, so the tier serving it
     * retries where the other short-lived tiers do not.
     */
    #[Test]
    public function the_realtime_tier_retries_a_push_that_failed_in_transit()
    {
        $this->extension('flarum-realtime');
        $this->app();

        $realtime = $this->supervisor('realtime');

        $this->assertSame(2, (int) $realtime['tries'], 'realtime pushes must survive a websocket restart.');

        // The short timeout is deliberate and stays: the whole broadcast measures
        // in milliseconds, and a push worth waiting seconds for is already stale.
        $this->assertSame(3, (int) $realtime['timeout']);
    }

    /**
     * `fast` is now an offer rather than a destination: nothing built in routes
     * onto it, so it stays scaled to zero unless an extension or operator puts
     * work there. Enabling realtime must no longer wake it.
     */
    #[Test]
    public function fast_stays_dormant_now_that_realtime_has_its_own_tier()
    {
        $this->extension('flarum-realtime');
        $this->app();

        $this->assertSame([], $this->supervisor('fast'), 'fast must not come online just because realtime is enabled.');
    }

    #[Test]
    public function gdpr_stays_dormant_when_the_extension_is_disabled()
    {
        $this->app();

        $this->assertSame([], $this->supervisor('long'));
        $this->assertNotContains('gdpr', $this->knownQueues());
    }

    #[Test]
    public function enabling_gdpr_brings_long_online_and_routes_gdpr_jobs()
    {
        $this->extension('flarum-gdpr');
        $this->app();

        $long = $this->supervisor('long');

        $this->assertNotEmpty($long, 'long must register once gdpr is enabled');
        $this->assertContains('gdpr', $long['queue']);
        $this->assertSame('gdpr', $this->routeFor(GdprJob::class));
        $this->assertContains('gdpr', $this->knownQueues());
    }

    #[Test]
    public function geoip_stays_unrouted_when_the_extension_is_disabled()
    {
        $this->app();

        $this->assertSame(['default'], $this->supervisor('standard')['queue']);
        $this->assertNotContains('iplookup', $this->knownQueues());
    }

    #[Test]
    public function enabling_geoip_adds_iplookup_to_standard_without_a_new_tier()
    {
        $this->extension('fof-geoip');
        $this->app();

        $standard = $this->supervisor('standard');

        // iplookup rides the existing standard pool — no dedicated supervisor,
        // and standard keeps its normal worker count.
        $this->assertContains('iplookup', $standard['queue']);
        $this->assertContains('default', $standard['queue']);
        $this->assertSame(6, $standard['processes']);
        $this->assertSame([], $this->supervisor('iplookup'), 'iplookup must not get its own supervisor');

        $this->assertSame('iplookup', $this->routeFor(RetrieveIP::class));
        $this->assertContains('iplookup', $this->knownQueues());
    }

    /**
     * Regression: with realtime + gdpr + geoip all enabled together, each job
     * class routes to its own queue. The previous shared-static mechanism
     * collided here — routing one overwrote the others, so realtime/mail jobs
     * silently landed on gdpr. Class-keyed routing keeps them independent.
     */
    #[Test]
    public function all_routed_extensions_together_keep_independent_queues()
    {
        $this->extension('flarum-realtime');
        $this->extension('flarum-gdpr');
        $this->extension('fof-geoip');
        $this->app();

        $this->assertSame('mail', $this->routeFor(SendEmailNotificationJob::class));
        $this->assertSame('realtime', $this->routeFor(RealtimeJob::class));
        $this->assertSame('gdpr', $this->routeFor(GdprJob::class));
        $this->assertSame('iplookup', $this->routeFor(RetrieveIP::class));
    }
}
