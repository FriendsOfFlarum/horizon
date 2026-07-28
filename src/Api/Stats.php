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
use FoF\Horizon\Traits\RetrievesRedisInfo;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Queue\Queue;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\WaitTimeCalculator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Stats implements RequestHandlerInterface
{
    use RetrievesRedisInfo;

    public function __construct(
        public Repository $config,
        public RedisManager $redis,
        public MetricsRepository $metrics,
        public JobRepository $jobs,
        public WaitTimeCalculator $waits,
        public SupervisorRepository $supervisors,
        public MasterSupervisorRepository $masters
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $info = $this->getInfo();

        if (Arr::has($info, 'error')) {
            return new JsonResponse([
                'error' => Arr::get($info, 'error'),
            ], 500);
        }

        $wait = collect($this->waits->calculate());
        $maxWait = $wait->max('minutes');
        $maxWaitQueue = $wait->where('minutes', $maxWait)->first();
        $status = $this->currentStatus();

        // Calculate memory percentage
        $memoryUsedBytes = Arr::get($info, 'Memory.used_memory', 0);
        $memoryMaxBytes = Arr::get($info, 'Memory.maxmemory', 0);
        $memoryPercentage = null;

        if ($memoryMaxBytes > 0) {
            $memoryPercentage = round(($memoryUsedBytes / $memoryMaxBytes) * 100, 2);
        }

        $evictionPolicy = Arr::get($info, 'Memory.maxmemory_policy', '');

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
            'maxWaitTime'   => $maxWait,
            'maxWaitQueue'  => $maxWaitQueue ? $maxWaitQueue->name : null,

            // Queue NAMES (or null when no metrics have been recorded yet).
            'busiestQueues' => [
                'slowestQueue'           => $this->metrics->queueWithMaximumRuntime(),
                'highestThroughputQueue' => $this->metrics->queueWithMaximumThroughput(),
            ],

            'health'    => (new HealthScore())->calculate($status, $maxWait, $maxWaitQueue?->name, $memoryPercentage),
            'timestamp' => time(),

            'redis' => [
                'memory_used'       => Arr::get($info, 'Memory.used_memory_human', '0'),
                'memory_used_bytes' => $memoryUsedBytes,
                'memory_peak'       => Arr::get($info, 'Memory.used_memory_peak_human', '0'),
                'memory_max'        => $this->formatMaxMemory(Arr::get($info, 'Memory.maxmemory_human', '0')),
                'memory_max_bytes'  => $memoryMaxBytes,
                'memory_percentage' => $memoryPercentage,
                'eviction_policy'   => $evictionPolicy,
                'ops_per_sec'       => Arr::get($info, 'Stats.instantaneous_ops_per_sec', 0),
                'connected_clients' => Arr::get($info, 'Clients.connected_clients', 0),
                'blocked_clients'   => Arr::get($info, 'Clients.blocked_clients', 0),
            ],
        ]);
    }

    /**
     * Get the total process count across all supervisors.
     *
     * @return int
     */
    protected function totalProcessCount(): int
    {
        $supervisors = $this->supervisors->all();

        /** @var \Illuminate\Support\Collection<int, object> $supervisorsCollection */
        $supervisorsCollection = collect($supervisors);

        return $supervisorsCollection->reduce(function ($carry, $supervisor) {
            /** @var array<int, int> $processes */
            $processes = $supervisor->processes;
            /** @var \Illuminate\Support\Collection<int, int> $processesCollection */
            $processesCollection = collect($processes);

            return $carry + $processesCollection->sum();
        }, 0);
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
     * Read from the shared cache using Illuminate's own key format so this
     * works regardless of the installed core version — a wildcard pause
     * covers every queue, otherwise any known queue being paused counts.
     */
    protected function queuePaused(): bool
    {
        $cache = resolve('cache.store');
        $connection = resolve(Queue::class)->getConnectionName();

        if ($cache->get("illuminate:queue:paused:{$connection}:*", false)) {
            return true;
        }

        foreach ($this->knownQueues() as $queue) {
            if ($cache->get("illuminate:queue:paused:{$connection}:{$queue}", false)) {
                return true;
            }
        }

        return false;
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

    private function formatMaxMemory(string $maxMemory): string
    {
        if ($maxMemory === '0' || $maxMemory === '0B') {
            return 'auto';
        }

        return $maxMemory;
    }
}
