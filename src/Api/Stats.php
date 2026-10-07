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

namespace FoF\Horizon\Api;

use FoF\Horizon\HealthScore;
use FoF\Horizon\HorizonMetrics;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Laminas\Diactoros\Response\JsonResponse;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class Stats implements RequestHandlerInterface
{
    public function __construct(
        public Repository $config,
        public RedisManager $redis,
        public MetricsRepository $metrics,
        public JobRepository $jobs,
        public WaitTimeCalculator $waits,
        public SupervisorRepository $supervisors,
        public MasterSupervisorRepository $masters,
        public HorizonMetrics $horizonMetrics
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $wait = collect($this->waits->calculate());
        // {queue, seconds} of the slowest queue (null only when no queues are
        // known). calculate() is a float map keyed "connection:queue" — a
        // previous version called ->max('minutes')/->first()->name on it,
        // which always produced null.
        $maxWait = $this->horizonMetrics->maxWait();
        $status = $this->currentStatus();

        return new JsonResponse([
            'status'        => $status,
            'pausedMasters' => $this->totalPausedMasters(),
            'processes'     => $this->totalProcessCount(),
            'jobsPerMinute' => $this->metrics->jobsProcessedPerMinute(),

            // Raw counts with their windows (the trim settings, in minutes):
            // the UI labels them from `periods` rather than guessing. No rate
            // is derived from these — recentJobs and failedJobs cover
            // different windows, so any ratio between them is meaningless.
            'recentJobs'  => $this->jobs->countRecent(),
            'failedJobs'  => $this->jobs->countRecentlyFailed(),
            'pendingJobs' => $this->jobs->countPending(),
            'periods'     => [
                'failedJobs' => $this->config->get('horizon.trim.recent_failed', $this->config->get('horizon.trim.failed')),
                'recentJobs' => $this->config->get('horizon.trim.recent'),
            ],

            'wait'          => $wait->take(1),
            'maxWaitTime'   => $maxWait['seconds'] ?? null,
            'maxWaitQueue'  => $maxWait['queue'] ?? null,

            // Queue NAMES (or null when no metrics have been recorded yet),
            // under the exact keys the stock Horizon dashboard SPA reads for
            // its "Max Runtime" and "Max Throughput" tiles — do not rename.
            'queueWithMaxRuntime'    => $this->metrics->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => $this->metrics->queueWithMaximumThroughput(),

            'health'    => (new HealthScore())->calculate($status, $maxWait !== null ? (float) $maxWait['seconds'] : null, $maxWait['queue'] ?? null, $this->memoryPercentage()),
            'timestamp' => time(),

        ]);
    }

    /**
     * Get the total process count across all supervisors.
     *
     * @return int
     */
    protected function totalProcessCount(): int
    {
        return $this->horizonMetrics->processes();
    }

    /**
     * Get the current status of Horizon.
     *
     * There are two independent ways jobs can stop being processed: the
     * Horizon master supervisor can be paused, or the queue can be paused
     * through Flarum's core queue-pause mechanism (the Advanced-page toggle /
     * queue:pause command). To an operator both mean the same thing — jobs
     * aren't flowing — so this reports a single unified "paused" whenever
     * either is in effect, rather than exposing two separate signals.
     *
     * @return string
     */
    protected function currentStatus(): string
    {
        if ($this->queuePaused()) {
            return 'paused';
        }

        if (!$masters = $this->masters->all()) {
            return 'inactive';
        }

        return collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        }) ? 'paused' : 'running';
    }

    /**
     * Whether the queue is paused through Flarum core's queue-pause mechanism.
     *
     * Delegates to the shared HorizonMetrics service so the dashboard endpoint
     * and the core-dashboard stats provider read pause state identically.
     */
    protected function queuePaused(): bool
    {
        return $this->horizonMetrics->queuePausedInCore($this->knownQueues());
    }

    /**
     * The queue names to check for an individual pause. Uses core's queue
     * registry when available (newer core), falling back to 'default'.
     *
     * @return string[]
     */
    protected function knownQueues(): array
    {
        $container = resolve(Container::class);

        if ($container->bound('flarum.queue.queues')) {
            return (array) $container->make('flarum.queue.queues');
        }

        return ['default'];
    }

    /**
     * Get the number of master supervisors that are currently paused.
     *
     * @return int
     */
    protected function totalPausedMasters(): int
    {
        if (!$masters = $this->masters->all()) {
            return 0;
        }

        return collect($masters)->filter(function ($master) {
            return $master->status === 'paused';
        })->count();
    }

    /**
     * Memory used on the Redis server Horizon runs on, as a percentage of its
     * `maxmemory`. Null when there's no limit, or no reading: memory is one
     * health factor, not a reason to fail the dashboard. The dashboard polls
     * this endpoint, so it asks for the memory section alone.
     */
    protected function memoryPercentage(): ?float
    {
        try {
            $info = $this->redis->connection('horizon')->command('info', ['memory']);
        } catch (Throwable) {
            return null;
        }

        // Predis nests the section under its name; phpredis returns it flat.
        $memory = is_array($info['Memory'] ?? null) ? $info['Memory'] : $info;
        $max = (int) ($memory['maxmemory'] ?? 0);

        return $max > 0 ? round((int) ($memory['used_memory'] ?? 0) / $max * 100, 2) : null;
    }
}
