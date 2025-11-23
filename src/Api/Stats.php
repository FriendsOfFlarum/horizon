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

use FoF\Horizon\Traits\RetrievesRedisInfo;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Contracts\Config\Repository;
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

        $recentJobs = $this->jobs->countRecent();
        $failedJobs = $this->jobs->countRecentlyFailed();
        $wait = collect($this->waits->calculate());
        $maxWait = $wait->max('minutes');
        $maxWaitQueue = $wait->where('minutes', $maxWait)->first();

        // Calculate success and failure rates
        $totalJobs = $recentJobs + $failedJobs;
        $successRate = $totalJobs > 0 ? round((($totalJobs - $failedJobs) / $totalJobs) * 100, 2) : 100;
        $failureRate = $totalJobs > 0 ? round(($failedJobs / $totalJobs) * 100, 2) : 0;

        // Calculate memory percentage
        $memoryUsedBytes = Arr::get($info, 'Memory.used_memory', 0);
        $memoryMaxBytes = Arr::get($info, 'Memory.maxmemory', 0);
        $memoryPercentage = null;

        if ($memoryMaxBytes > 0) {
            $memoryPercentage = round(($memoryUsedBytes / $memoryMaxBytes) * 100, 2);
        }

        return new JsonResponse([
            'failedJobs'             => $failedJobs,
            'jobsPerMinute'          => $this->metrics->jobsProcessedPerMinute(),
            'pausedMasters'          => $this->totalPausedMasters(),
            'periods'                => [
                'failedJobs'     => $this->config->get('horizon.trim.recent_failed', $this->config->get('horizon.trim.failed')),
                'recentJobs'     => $this->config->get('horizon.trim.recent'),
            ],
            'processes'              => $this->totalProcessCount(),
            'queueWithMaxRuntime'    => $this->metrics->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => $this->metrics->queueWithMaximumThroughput(),
            'recentJobs'             => $recentJobs,
            'status'                 => $this->currentStatus(),
            'wait'                   => $wait->take(1),
            'maxWaitTime'            => $maxWait,
            'maxWaitQueue'           => $maxWaitQueue ? $maxWaitQueue->name : null,
            'successRate'            => $successRate,
            'failureRate'            => $failureRate,
            'healthScore'            => $this->calculateHealthScore($failureRate, $memoryPercentage, $this->currentStatus()),
            'timestamp'              => time(),
            'redis_stats'            => [
                'memory_used'          => Arr::get($info, 'Memory.used_memory_human', '0'),
                'memory_used_bytes'    => $memoryUsedBytes,
                'memory_peak'          => Arr::get($info, 'Memory.used_memory_peak_human', '0'),
                'memory_max'           => $this->formatMaxMemory(Arr::get($info, 'Memory.maxmemory_human', '0')),
                'memory_max_bytes'     => $memoryMaxBytes,
                'memory_percentage'    => $memoryPercentage,
                'memory_max_policy'    => Arr::get($info, 'Memory.maxmemory_policy', ''),
                'ops_per_sec'          => Arr::get($info, 'Stats.instantaneous_ops_per_sec', 0),
                'connected_clients'    => Arr::get($info, 'Clients.connected_clients', 0),
                'blocked_clients'      => Arr::get($info, 'Clients.blocked_clients', 0),
            ],
        ]);
    }

    /**
     * Get the total process count across all supervisors.
     *
     * @return int
     */
    protected function totalProcessCount()
    {
        $supervisors = $this->supervisors->all();

        return collect($supervisors)->reduce(function ($carry, $supervisor) {
            return $carry + collect($supervisor->processes)->sum();
        }, 0);
    }

    /**
     * Get the current status of Horizon.
     *
     * @return string
     */
    protected function currentStatus()
    {
        if (!$masters = $this->masters->all()) {
            return 'inactive';
        }

        return collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        }) ? 'paused' : 'running';
    }

    /**
     * Get the number of master supervisors that are currently paused.
     *
     * @return int
     */
    protected function totalPausedMasters()
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

    /**
     * Calculate health score based on various metrics.
     *
     * @param  float  $failureRate
     * @param  float|null  $memoryPercentage
     * @param  string  $status
     * @return int
     */
    private function calculateHealthScore(float $failureRate, ?float $memoryPercentage, string $status): int
    {
        $score = 100;

        // Deduct points for failure rate
        if ($failureRate > 50) {
            $score -= 40;
        } elseif ($failureRate > 20) {
            $score -= 25;
        } elseif ($failureRate > 10) {
            $score -= 15;
        } elseif ($failureRate > 5) {
            $score -= 5;
        }

        // Deduct points for memory usage
        if ($memoryPercentage !== null) {
            if ($memoryPercentage > 90) {
                $score -= 30;
            } elseif ($memoryPercentage > 75) {
                $score -= 15;
            }
        }

        // Deduct points for inactive or paused status
        if ($status === 'inactive') {
            $score -= 50;
        } elseif ($status === 'paused') {
            $score -= 20;
        }

        return max(0, $score);
    }
}
