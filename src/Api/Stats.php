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

    /**
     * @var Repository
     */
    protected $config;

    /**
     * @var RedisManager
     */
    protected $redis;

    /**
     * @var MetricsRepository
     */
    protected $metricsRepository;

    /**
     * @var JobRepository
     */
    protected $jobRepository;

    /**
     * @var WaitTimeCalculator
     */
    protected $waitTimeCalculator;

    /**
     * @var SupervisorRepository
     */
    protected $supervisorRepository;

    /**
     * @var MasterSupervisorRepository
     */
    protected $masterSupervisorRepository;

    public function __construct(Repository $config, RedisManager $redis, MetricsRepository $metricsRepository, JobRepository $jobRepository, WaitTimeCalculator $waitTimeCalculator, SupervisorRepository $supervisorRepository, MasterSupervisorRepository $masterSupervisorRepository)
    {
        $this->config = $config;
        $this->redis = $redis;
        $this->metricsRepository = $metricsRepository;
        $this->jobRepository = $jobRepository;
        $this->waitTimeCalculator = $waitTimeCalculator;
        $this->supervisorRepository = $supervisorRepository;
        $this->masterSupervisorRepository = $masterSupervisorRepository;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $info = $this->getInfo();

        if ($error = Arr::get($info, 'error')) {
            return new JsonResponse([
                'error' => $error,
            ], 200);
        }

        $recentJobs = $this->jobRepository->countRecent();
        $recentlyFailed = $this->jobRepository->countRecentlyFailed();
        $jobsPerMinute = $this->metricsRepository->jobsProcessedPerMinute();
        $processes = $this->totalProcessCount();

        // Calculate wait times
        $waitTimes = collect($this->waitTimeCalculator->calculate());
        $maxWaitTime = $waitTimes->max('minutes');
        $maxWaitQueue = $waitTimes->where('minutes', $maxWaitTime)->first();

        // Calculate memory statistics
        $memoryUsedBytes = Arr::get($info, 'Memory.used_memory', 0);
        $memoryMaxBytes = Arr::get($info, 'Memory.maxmemory', 0);
        $memoryPercentage = $memoryMaxBytes > 0 ? round(($memoryUsedBytes / $memoryMaxBytes) * 100, 1) : null;

        // Calculate health metrics
        $failureRate = $recentJobs > 0 ? round(($recentlyFailed / $recentJobs) * 100, 2) : 0;
        $successRate = $recentJobs > 0 ? round((($recentJobs - $recentlyFailed) / $recentJobs) * 100, 2) : 100;

        return new JsonResponse([
            // Laravel Horizon standard fields
            'failedJobs'             => $recentlyFailed,
            'jobsPerMinute'          => $jobsPerMinute,
            'pausedMasters'          => $this->totalPausedMasters(),
            'periods'                => [
                'failedJobs' => $this->config->get('horizon.trim.recent_failed', $this->config->get('horizon.trim.failed')),
                'recentJobs' => $this->config->get('horizon.trim.recent'),
            ],
            'processes'              => $processes,
            'queueWithMaxRuntime'    => $this->metricsRepository->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => $this->metricsRepository->queueWithMaximumThroughput(),
            'recentJobs'             => $recentJobs,
            'status'                 => $this->currentStatus(),
            'wait'                   => $waitTimes->take(1),
            // Flarum-specific: backwards compatibility for widget
            'recentlyFailed'         => $recentlyFailed,
            // Flarum-specific: Enhanced wait time data
            'maxWaitTime'            => $maxWaitTime ? round($maxWaitTime, 2) : 0,
            'maxWaitQueue'           => $maxWaitQueue ? $maxWaitQueue->name : null,
            // Flarum-specific: Calculated health metrics
            'failureRate'            => $failureRate,
            'successRate'            => $successRate,
            'healthScore'            => $this->calculateHealthScore($failureRate, $memoryPercentage, $processes),
            // Flarum-specific: Redis stats for widget
            'redis_stats'            => [
                'memory_used'        => Arr::get($info, 'Memory.used_memory_human', '0'),
                'memory_used_bytes'  => $memoryUsedBytes,
                'memory_peak'        => Arr::get($info, 'Memory.used_memory_peak_human', '0'),
                'memory_max'         => $this->formatMaxMemory(Arr::get($info, 'Memory.maxmemory_human', '0')),
                'memory_max_bytes'   => $memoryMaxBytes,
                'memory_percentage'  => $memoryPercentage,
                'memory_max_policy'  => Arr::get($info, 'Memory.maxmemory_policy', ''),
                'ops_per_sec'        => Arr::get($info, 'Stats.instantaneous_ops_per_sec', 0),
                'connected_clients'  => Arr::get($info, 'Clients.connected_clients', 0),
                'blocked_clients'    => Arr::get($info, 'Clients.blocked_clients', 0),
            ],
            // Timestamp for tracking data freshness
            'timestamp'              => time(),
        ]);
    }

    /**
     * Calculate an overall health score (0-100) based on multiple factors
     */
    protected function calculateHealthScore(float $failureRate, ?float $memoryPercentage, int $processes): int
    {
        $score = 100;

        // Deduct points for high failure rate
        if ($failureRate > 50) {
            $score -= 50;
        } elseif ($failureRate > 20) {
            $score -= 30;
        } elseif ($failureRate > 10) {
            $score -= 15;
        } elseif ($failureRate > 5) {
            $score -= 5;
        }

        // Deduct points for high memory usage
        if ($memoryPercentage !== null) {
            if ($memoryPercentage >= 95) {
                $score -= 30;
            } elseif ($memoryPercentage >= 85) {
                $score -= 20;
            } elseif ($memoryPercentage >= 75) {
                $score -= 10;
            }
        }

        // Deduct points if no processes are running
        if ($processes === 0) {
            $score -= 50;
        }

        return max(0, $score);
    }

    protected function totalProcessCount(): int
    {
        $supervisors = $this->supervisorRepository->all();

        return collect($supervisors)->reduce(function ($carry, $supervisor) {
            return $carry + collect($supervisor->processes)->sum();
        }, 0);
    }

    protected function currentStatus(): string
    {
        if (!$masters = $this->masterSupervisorRepository->all()) {
            return 'inactive';
        }

        return collect($masters)->every(function ($master) {
            return $master->status === 'paused';
        }) ? 'paused' : 'running';
    }

    protected function totalPausedMasters(): int
    {
        if (!$masters = $this->masterSupervisorRepository->all()) {
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
