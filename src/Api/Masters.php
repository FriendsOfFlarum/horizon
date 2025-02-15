<?php

/*
 * This file is part of flarum/horizon.
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

    public function __construct(MasterSupervisorRepository $masters, SupervisorRepository $supervisors)
    {
        $this->masters = $masters;
        $this->supervisors = $supervisors;
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
            $master->supervisors = $supervisors->get($name);
        });
    }
}
