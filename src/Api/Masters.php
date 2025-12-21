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

use Laminas\Diactoros\Response\JsonResponse;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\ProvisioningPlan;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Masters implements RequestHandlerInterface
{
    public function __construct(
        public MasterSupervisorRepository $masters,
        public SupervisorRepository $supervisors
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse($this->index());
    }

    /**
     * @return \Illuminate\Support\Collection<string, object>
     */
    protected function index(): \Illuminate\Support\Collection
    {
        // Get all masters and key by name
        /** @var \Illuminate\Support\Collection<string, object> $masters */
        $masters = collect($this->masters->all())
            ->keyBy('name')
            ->sortBy('name');

        // Group all supervisors by their "master" field
        /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, object>> $supervisors */
        $supervisors = collect($this->supervisors->all())
            ->sortBy('name')
            ->groupBy('master');

        // For each master, merge in supervisors from the repository and from the provisioning plan.
        return $masters->each(function ($master, $masterName) use ($supervisors) {
            // Get the supervisors for this master from the repository (or an empty collection)
            $collection = $supervisors->get($masterName, collect());
            // Merge in the provisioning plan supervisors, which have a default inactive status.
            /** @var array<string, mixed> $planConfig */
            $planConfig = ProvisioningPlan::get($masterName)
                ->plan[$master->environment ?? config('horizon.env') ?? config('app.env')] ?? [];

            $planSupervisors = collect($planConfig)
                ->map(function ($value, $key) use ($masterName) {
                    return (object) [
                        'name'      => "{$masterName}:{$key}",
                        'master'    => $masterName,
                        'status'    => 'inactive',
                        'processes' => [],
                        'options'   => [
                            'queue'   => array_key_exists('queue', $value) && is_array($value['queue'])
                                ? implode(',', $value['queue'])
                                : ($value['queue'] ?? ''),
                            'balance' => $value['balance'] ?? null,
                        ],
                    ];
                })
                ->values();

            $master->supervisors = $collection
                ->merge($planSupervisors->all())
                ->unique('name')
                ->values();
        });
    }
}
