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
use FoF\Horizon\SettingLocks;
use FoF\Redis\Extend\Redis;
use PHPUnit\Framework\Attributes\Test;

/**
 * A setting configured by a higher-precedence layer (env / config.php /
 * extend.php) can't be changed from the admin UI, so the admin page disables
 * that field. SettingLocks reports which settings are pinned and by which
 * source (env distinctly; config.php and extend.php grouped as "code", since
 * they merge into the same config and can't be told apart afterwards) so the
 * frontend can do that.
 */
class SettingLocksTest extends TestCase
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

    private function locks(): array
    {
        return $this->app()->getContainer()->make(SettingLocks::class)->locks();
    }

    #[Test]
    public function nothing_is_locked_by_default()
    {
        $this->assertSame([], $this->locks());
    }

    #[Test]
    public function an_admin_only_setting_is_not_locked()
    {
        // A value set purely via the admin settings store is editable, so it is
        // NOT a lock.
        $this->setting('fof-horizon.email_concurrency', '4');

        $this->assertArrayNotHasKey('email_concurrency', $this->locks());
    }

    #[Test]
    public function an_environment_variable_locks_with_source_env()
    {
        putenv('REDIS_HORIZON_EMAIL_CONCURRENCY=8');

        try {
            $locks = $this->locks();

            $this->assertArrayHasKey('email_concurrency', $locks);
            $this->assertSame('env', $locks['email_concurrency']['source']);
            $this->assertSame('8', (string) $locks['email_concurrency']['value']);
        } finally {
            putenv('REDIS_HORIZON_EMAIL_CONCURRENCY');
        }
    }

    #[Test]
    public function a_config_php_value_locks_with_source_code()
    {
        $this->config('horizon', ['email_concurrency' => 5]);

        $locks = $this->locks();

        $this->assertArrayHasKey('email_concurrency', $locks);
        $this->assertSame('code', $locks['email_concurrency']['source']);
        $this->assertSame(5, $locks['email_concurrency']['value']);
    }

    #[Test]
    public function the_extender_locks_email_concurrency_with_source_code()
    {
        $this->extend((new Horizon())->emailConcurrency(6));

        $locks = $this->locks();

        $this->assertArrayHasKey('email_concurrency', $locks);
        $this->assertSame('code', $locks['email_concurrency']['source']);
        $this->assertSame(6, $locks['email_concurrency']['value']);
    }

    #[Test]
    public function trim_and_memory_limit_are_lockable_too()
    {
        $this->config('horizon', ['memory_limit' => 256, 'trim' => ['recent' => 42]]);

        putenv('REDIS_HORIZON_TRIM_FAILED=99');

        try {
            $locks = $this->locks();

            $this->assertSame('code', $locks['memory_limit']['source']);
            $this->assertSame(256, $locks['memory_limit']['value']);
            $this->assertSame('code', $locks['trim.recent']['source']);
            $this->assertSame('env', $locks['trim.failed']['source']);
        } finally {
            putenv('REDIS_HORIZON_TRIM_FAILED');
        }
    }
}
