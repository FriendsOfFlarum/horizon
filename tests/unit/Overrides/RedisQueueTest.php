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

namespace FoF\Horizon\Tests\unit\Overrides;

use FoF\Horizon\Overrides\RedisQueue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class RedisQueueTest extends TestCase
{
    /**
     * Horizon's RedisQueue::later() reads Illuminate\Foundation\Application::VERSION,
     * which only exists in a full laravel/framework install. Flarum ships discrete
     * illuminate/* components, so we must declare our own later() that never
     * references that class.
     *
     * @see https://github.com/laravel/horizon/pull/1798
     * @see https://github.com/laravel/horizon/pull/1810
     */
    #[Test]
    public function later_is_overridden_locally_so_it_cannot_touch_foundation_application()
    {
        $method = new ReflectionMethod(RedisQueue::class, 'later');

        $this->assertSame(
            RedisQueue::class,
            $method->getDeclaringClass()->getName(),
            'later() must be declared by our override, not inherited from Horizon.'
        );
    }

    #[Test]
    public function our_later_does_not_reference_foundation_application()
    {
        $method = new ReflectionMethod(RedisQueue::class, 'later');
        $file = file($method->getFileName());
        $body = implode('', array_slice(
            $file,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        $this->assertStringNotContainsString('Application', $body);
    }

    /**
     * The four-argument form is what upstream wanted the version guard to select;
     * it is what keeps the delay in the stored payload. Guard the arity so a
     * future illuminate/queue change cannot silently drop $delay again.
     */
    #[Test]
    public function create_payload_accepts_the_delay_argument()
    {
        $method = new ReflectionMethod(RedisQueue::class, 'createPayload');

        $this->assertGreaterThanOrEqual(
            4,
            $method->getNumberOfParameters(),
            'createPayload() must accept $delay for later() to pass it through.'
        );
    }
}
