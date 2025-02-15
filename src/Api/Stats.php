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
    private $config;

    /**
     * @var RedisManager
     */
    private $redis;

    public function __construct(Repository $config, RedisManager $redis)
    {
        $this->config = $config;
        $this->redis = $redis;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse([
            'jobsPerMinute'          => resolve(MetricsRepository::class)->jobsProcessedPerMinute(),
            'processes'              => $this->totalProcessCount(),
            'queueWithMaxRuntime'    => resolve(MetricsRepository::class)->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => resolve(MetricsRepository::class)->queueWithMaximumThroughput(),
            'recentlyFailed'         => resolve(JobRepository::class)->countRecentlyFailed(),
            'recentJobs'             => resolve(JobRepository::class)->countRecent(),
            'status'                 => $this->currentStatus(),
            'wait'                   => collect(resolve(WaitTimeCalculator::class)->calculate())->take(1),
            'periods'                => [
                'recentJobs'     => $this->config->get('horizon.trim.recent'),
                'recentlyFailed' => $this->config->get('horizon.trim.failed'),
            ],
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

    protected function totalProcessCount()
    {
        $supervisors = resolve(SupervisorRepository::class)->all();

        return collect($supervisors)->reduce(function ($carry, $supervisor) {
            return $carry + collect($supervisor->processes)->sum();
        }, 0);
    }

    protected function currentStatus()
    {
        if (!$masters = resolve(MasterSupervisorRepository::class)->all()) {
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
