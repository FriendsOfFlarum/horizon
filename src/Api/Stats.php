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
    public function __construct(
        public Repository $config,
        public RedisManager $redis,
        public MetricsRepository $metrics,
        public JobRepository $jobs,
        public WaitTimeCalculator $waits,
        public SupervisorRepository $supervisors,
        public MasterSupervisorRepository $masters
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'failedJobs'             => $this->jobs->countRecentlyFailed(),
            'jobsPerMinute'          => $this->metrics->jobsProcessedPerMinute(),
            'pausedMasters'          => $this->totalPausedMasters(),
            'periods'                => [
                'recentJobs'     => $this->config->get('horizon.trim.recent'),
                'recentlyFailed' => $this->config->get('horizon.trim.failed'),
            ],
            'processes'              => $this->totalProcessCount(),
            'queueWithMaxRuntime'    => $this->metrics->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => $this->metrics->queueWithMaximumThroughput(),
            'recentJobs'             => $this->jobs->countRecent(),
            'status'                 => $this->currentStatus(),
            'wait'                   => collect($this->waits->calculate())->take(1),
            'redis_stats'            => [
                'memory_used'       => Arr::get($this->getInfo(), 'Memory.used_memory_human', 0),
                'memory_peak'       => Arr::get($this->getInfo(), 'Memory.used_memory_peak_human', 0),
                'memory_max'        => $this->formatMaxMemory(Arr::get($this->getInfo(), 'Memory.maxmemory_human', 0)),
                'memory_max_policy' => Arr::get($this->getInfo(), 'Memory.maxmemory_policy', ''),
                'cpu_user'          => Arr::get($this->getInfo(), 'CPU.used_cpu_user', 0),
                'cpu_sys'           => Arr::get($this->getInfo(), 'CPU.used_cpu_sys', 0),
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
        if (! $masters = $this->masters->all()) {
            return 0;
        }

        return collect($masters)->filter(function ($master) {
            return $master->status === 'paused';
        })->count();
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
