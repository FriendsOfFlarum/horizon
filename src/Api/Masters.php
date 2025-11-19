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

use Illuminate\Contracts\Config\Repository;
use Laminas\Diactoros\Response\JsonResponse;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\ProvisioningPlan;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Masters implements RequestHandlerInterface
{
    /**
     * @var MasterSupervisorRepository
     */
    private $masters;
    /**
     * @var SupervisorRepository
     */
    private $supervisors;
    /**
     * @var Repository
     */
    private $config;

    public function __construct(MasterSupervisorRepository $masters, SupervisorRepository $supervisors, Repository $config)
    {
        $this->masters = $masters;
        $this->supervisors = $supervisors;
        $this->config = $config;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse($this->index());
    }

    protected function index()
    {
        $masters = collect($this->masters->all())->keyBy('name')->sortBy('name');

        $supervisors = collect($this->supervisors->all())->sortBy('name')->groupBy('master');

        return $masters->each(function ($master, $name) use ($supervisors) {
            $master->supervisors = ($supervisors->get($name) ?? collect())
                ->merge(
                    collect(ProvisioningPlan::get($name)->plan[$master->environment ?? $this->config->get('horizon.env', $this->config->get('app.env'))] ?? [])
                        ->map(function ($value, $key) use ($name) {
                            return (object) [
                                'name'      => $name.':'.$key,
                                'master'    => $name,
                                'status'    => 'inactive',
                                'processes' => [],
                                'options'   => [
                                    'queue'   => array_key_exists('queue', $value) && is_array($value['queue']) ? implode(',', $value['queue']) : ($value['queue'] ?? ''),
                                    'balance' => $value['balance'] ?? null,
                                ],
                            ];
                        })
                )
                ->unique('name')
                ->values();
        });
    }
}
