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

        return new JsonResponse([
            'jobsPerMinute'          => $this->metricsRepository->jobsProcessedPerMinute(),
            'processes'              => $this->totalProcessCount(),
            'queueWithMaxRuntime'    => $this->metricsRepository->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => $this->metricsRepository->queueWithMaximumThroughput(),
            'recentlyFailed'         => $this->jobRepository->countRecentlyFailed(),
            'recentJobs'             => $this->jobRepository->countRecent(),
            'status'                 => $this->currentStatus(),
            'wait'                   => collect($this->waitTimeCalculator->calculate())->take(1),
            'periods'                => [
                'recentJobs'     => $this->config->get('horizon.trim.recent'),
                'recentlyFailed' => $this->config->get('horizon.trim.failed'),
            ],
            'redis_stats' => [
                'memory_used'       => Arr::get($info, 'Memory.used_memory_human', '0'),
                'memory_peak'       => Arr::get($info, 'Memory.used_memory_peak_human', '0'),
                'memory_max'        => $this->formatMaxMemory(Arr::get($info, 'Memory.maxmemory_human', '0')),
                'memory_max_policy' => Arr::get($info, 'Memory.maxmemory_policy', ''),
                'ops_per_sec'       => Arr::get($info, 'Stats.instantaneous_ops_per_sec', 0),
                'connected_clients' => Arr::get($info, 'Clients.connected_clients', 0),
                'blocked_clients'   => Arr::get($info, 'Clients.blocked_clients', 0),
            ],
        ]);
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

        return collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        }) ? 'paused' : 'running';
    }

    private function getInfo(): array
    {
        return $this->redis->connection()->info();
    }

    private function formatMaxMemory(string $maxMemory): string
    {
        if ($maxMemory === '0' || $maxMemory === '0B') {
            return 'auto';
        }

        return $maxMemory;
    }
}
