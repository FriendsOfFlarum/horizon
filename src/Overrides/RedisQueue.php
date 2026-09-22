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

namespace FoF\Horizon\Overrides;

use Laravel\Horizon\Events\JobPending;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\JobPayload;
use Laravel\Horizon\RedisQueue as HorizonBaseQueue;

class RedisQueue extends HorizonBaseQueue
{
    /**
     * Push a new job onto the queue.
     *
     * @param object|string $job
     * @param mixed         $data
     * @param string|null   $queue
     *
     * @return mixed
     */
    public function push($job, $data = '', $queue = null)
    {
        /** @phpstan-ignore-next-line */
        if ($job->queue && !$queue) {
            /** @phpstan-ignore-next-line */
            $queue = $job->queue;
        }

        return parent::push($job, $data, $queue);
    }

    /**
     * Push a new job onto the queue after a delay.
     *
     * Reproduces Laravel\Horizon\RedisQueue::later() with two omissions. Every
     * path reachable on Flarum is kept; what is dropped cannot execute here.
     *
     * 1. The version guard, which is the bug. Upstream reads
     *    `Illuminate\Foundation\Application::VERSION` to decide whether to
     *    forward $delay to createPayload(). That class ships only with a full
     *    laravel/framework install — Flarum installs discrete illuminate/*
     *    components, so on Flarum the guard fatals with "Class not found".
     *    Flarum requires illuminate/* ^13.0 and createPayload() has accepted
     *    $delay since well before that floor, so we pass it unconditionally.
     *
     * 2. Upstream's `method_exists($this, 'enqueueUsing')` guard and the
     *    fallback after it. enqueueUsing() is inherited from
     *    Illuminate\Queue\Queue and has existed since Laravel 8, so the guard
     *    is always true and the fallback is unreachable below Flarum's floor.
     *    It is also the branch we want: enqueueUsing() applies the job-queueing
     *    hooks and after-commit dispatch that the fallback skips.
     *
     * Remove this override entirely if upstream stops referencing
     * Foundation\Application.
     *
     * @see https://github.com/laravel/horizon/pull/1798 (introduced the import)
     * @see https://github.com/laravel/horizon/pull/1810 (made it a hard fatal)
     *
     * @param \DateTimeInterface|\DateInterval|int $delay
     * @param string                               $job
     * @param mixed                                $data
     * @param string|null                          $queue
     *
     * @return mixed
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        $payload = (new JobPayload($this->createPayload($job, $queue, $data, $delay)))->prepare($job)->value;

        return $this->enqueueUsing(
            $job,
            $payload,
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                $this->event($this->getQueue($queue), new JobPending($payload));

                return tap(parent::laterRaw($delay, $payload, $queue), function () use ($payload, $queue) {
                    $this->event($this->getQueue($queue), new JobPushed($payload));
                });
            }
        );
    }
}
