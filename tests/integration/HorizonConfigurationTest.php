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

    /**
     * @return array<string, array<string, mixed>> supervisor name => config
     */
    private function supervisors(): array
    {
        $config = $this->horizonConfig();
        $env = $this->app()->getContainer()->make('env');

        return $config['environments'][$env] ?? [];
    }

    private function supervisor(string $name): array
    {
        return $this->supervisors()['supervisor-'.$name] ?? [];
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
    public function a_fresh_site_runs_standard_and_emails()
    {
        // standard is always active; emails is active out of the box too (every
        // install sends mail). fast/long stay dormant until their extension is
        // enabled or an operator brings them online.
        $names = array_keys($this->supervisors());
        sort($names);

        $this->assertSame(['supervisor-emails', 'supervisor-standard'], $names);
    }

    #[Test]
    public function the_emails_profile_serves_the_mail_queue_out_of_the_box()
    {
        $emails = $this->supervisor('emails');

        $this->assertSame(['mail'], $emails['queue']);
        $this->assertSame(3, $emails['tries']);
        $this->assertSame(120, $emails['timeout']);
    }

    #[Test]
    public function the_mail_queue_is_registered_for_admin_tooling()
    {
        $this->assertContains('mail', $this->app()->getContainer()->make('flarum.queue.queues'));
    }

    #[Test]
    public function email_concurrency_defaults_to_the_emails_profile_worker_count()
    {
        // Unset everywhere → the emails profile's own default (1 process).
        $this->assertSame(1, $this->supervisor('emails')['processes']);
    }

    #[Test]
    public function email_concurrency_is_set_via_config_php()
    {
        $this->config('horizon', ['email_concurrency' => 4]);

        $this->assertSame(4, $this->supervisor('emails')['processes']);
    }

    #[Test]
    public function email_concurrency_is_set_via_the_admin_setting()
    {
        $this->setting('fof-horizon.email_concurrency', '3');

        $this->assertSame(3, $this->supervisor('emails')['processes']);
    }

    #[Test]
    public function email_concurrency_is_set_via_the_extender()
    {
        $this->extend((new Horizon())->emailConcurrency(6));

        $this->assertSame(6, $this->supervisor('emails')['processes']);
    }

    #[Test]
    public function email_concurrency_env_wins_over_config_php()
    {
        $this->config('horizon', ['email_concurrency' => 4]);
        putenv('REDIS_HORIZON_EMAIL_CONCURRENCY=9');

        try {
            $this->assertSame(9, $this->supervisor('emails')['processes']);
        } finally {
            putenv('REDIS_HORIZON_EMAIL_CONCURRENCY');
        }
    }

    #[Test]
    public function config_php_email_concurrency_wins_over_the_admin_setting()
    {
        $this->setting('fof-horizon.email_concurrency', '3');
        $this->config('horizon', ['email_concurrency' => 7]);

        $this->assertSame(7, $this->supervisor('emails')['processes']);
    }

    #[Test]
    public function email_concurrency_wins_over_a_generic_emails_process_override()
    {
        // The dedicated knob is the single obvious control: it beats a raw
        // per-profile process count.
        $this->config('horizon', [
            'email_concurrency' => 5,
            'supervisors'       => ['emails' => ['processes' => 99]],
        ]);

        $this->assertSame(5, $this->supervisor('emails')['processes']);
    }

    #[Test]
    public function non_numeric_email_concurrency_throws()
    {
        // Use the env surface (read at boot) to keep the throw inside the
        // supervisor()/app-boot call the expectation guards.
        putenv('REDIS_HORIZON_EMAIL_CONCURRENCY=lots');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->supervisor('emails');
        } finally {
            putenv('REDIS_HORIZON_EMAIL_CONCURRENCY');
        }
    }

    /**
     * The vendored horizon.php ships a `defaults` block with a `supervisor-1`
     * template that Horizon's ProvisioningPlan merges into every environment
     * (array_replace_recursive), which would resurrect `supervisor-1` alongside
     * our profiles at provision time — invisible to the assembled `environments`
     * but very visible in the running master. We clear it; keep it cleared.
     */
    #[Test]
    public function horizon_defaults_are_cleared_so_supervisor_1_cannot_leak()
    {
        $this->assertSame([], $this->horizonConfig()['defaults'] ?? ['not-cleared']);
    }

    #[Test]
    public function the_standard_supervisor_matches_the_documented_baseline()
    {
        $standard = $this->supervisor('standard');

        $this->assertSame(['default'], $standard['queue']);
        $this->assertSame('auto', $standard['balance']);
        $this->assertSame(6, $standard['processes']); // base 1 × multiplier 6
        $this->assertSame(1, $standard['tries']);
        $this->assertSame(60, $standard['timeout']);
        $this->assertSame(5, $standard['nice']); // arbitrary pass-through key survives
    }

    #[Test]
    public function a_dormant_tier_comes_online_via_env()
    {
        // A tier needs at least one queue to come online; route one, then scale
        // it up via env.
        $this->extend((new Horizon())->queueOn('fast', 'realtime'));

        putenv('REDIS_HORIZON_FAST_MAX_PROCESSES=8');

        try {
            $fast = $this->supervisor('fast');

            $this->assertSame(8, $fast['processes']);
            $this->assertSame(3, $fast['timeout']); // fast's baked timeout
            $this->assertSame(['realtime'], $fast['queue']);
        } finally {
            putenv('REDIS_HORIZON_FAST_MAX_PROCESSES');
        }
    }

    #[Test]
    public function config_php_can_bring_a_tier_online()
    {
        $this->config('horizon', [
            'supervisors' => ['long' => ['processes' => 3, 'queues' => ['exports']]],
        ]);

        $this->assertSame(3, $this->supervisor('long')['processes']);
        $this->assertSame(['exports'], $this->supervisor('long')['queue']);
    }

    #[Test]
    public function env_wins_over_config_php()
    {
        // The horizon config is assembled once per boot, so env-vs-config
        // precedence must be exercised in a single fresh assembly (not by
        // re-reading after a putenv within the same booted app).
        $this->config('horizon', [
            'supervisors' => ['long' => ['processes' => 3, 'queues' => ['exports']]],
        ]);

        putenv('REDIS_HORIZON_LONG_MAX_PROCESSES=5');

        try {
            $this->assertSame(5, $this->supervisor('long')['processes']);
        } finally {
            putenv('REDIS_HORIZON_LONG_MAX_PROCESSES');
        }
    }

    #[Test]
    public function bringing_a_tier_online_with_no_queues_throws()
    {
        // fast has workers requested but no queue routed to it: a
        // misconfiguration that would otherwise silently drain `default`.
        putenv('REDIS_HORIZON_FAST_MAX_PROCESSES=3');

        try {
            $this->expectException(\InvalidArgumentException::class);
            $this->supervisor('fast');
        } finally {
            putenv('REDIS_HORIZON_FAST_MAX_PROCESSES');
        }
    }

    #[Test]
    public function the_extender_can_override_a_profile()
    {
        $this->extend(
            (new Horizon())->supervisor('fast', ['queues' => ['realtime'], 'processes' => 12])
        );

        $fast = $this->supervisor('fast');

        $this->assertSame(12, $fast['processes']);
        $this->assertSame(['realtime'], $fast['queue']);
    }

    #[Test]
    public function queue_on_appends_to_a_supervisor_without_replacing_its_list()
    {
        $this->extend(
            (new Horizon())
                ->supervisor('long', ['queues' => ['exports'], 'processes' => 1])
                ->queueOn('long', 'gdpr', 'migration-high')
        );

        $this->assertSame(['exports', 'gdpr', 'migration-high'], $this->supervisor('long')['queue']);
    }

    #[Test]
    public function routed_queues_are_registered_in_the_core_known_queues_registry()
    {
        $this->extend(
            (new Horizon())
                ->supervisor('fast', ['queues' => ['realtime'], 'processes' => 4])
                ->queueOn('long', 'exports')
        );

        $known = $this->app()->getContainer()->make('flarum.queue.queues');

        $this->assertContains('realtime', $known);
        $this->assertContains('exports', $known);
    }

    #[Test]
    public function memory_limit_is_raised_to_the_largest_supervisor_budget()
    {
        // Bring long online with a 512MB budget (base 128 × multiplier 4).
        $this->config('horizon', [
            'supervisors' => ['long' => ['processes' => 1, 'queues' => ['exports']]],
        ]);

        $this->assertSame(512, (int) $this->horizonConfig()['memory_limit']);
    }

    #[Test]
    public function trim_settings_follow_the_same_layering()
    {
        $this->setting('fof-horizon.trim.recent', '90');

        putenv('REDIS_HORIZON_TRIM_COMPLETED=120');

        try {
            $config = $this->horizonConfig();

            $this->assertSame(90, (int) $config['trim']['recent']);   // settings UI
            $this->assertSame(120, (int) $config['trim']['completed']); // env override
            $this->assertSame(60, (int) $config['trim']['pending']);   // untouched default
        } finally {
            putenv('REDIS_HORIZON_TRIM_COMPLETED');
        }
    }

    /**
     * Core registers queue:retry / queue:failed CLI commands whenever the
     * connection is not sync. With horizon active, failed jobs must be
     * persisted; fof/horizon defers to fof/redis's Redis-backed failer rather
     * than forcing the database failer.
     */
    #[Test]
    public function failed_jobs_are_persisted_not_discarded()
    {
        $failer = $this->app()->getContainer()->make('queue.failer');

        $this->assertInstanceOf(RedisFailedJobProvider::class, $failer);
    }
}
