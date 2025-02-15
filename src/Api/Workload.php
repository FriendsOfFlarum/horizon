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
use Laravel\Horizon\Contracts\WorkloadRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Workload implements RequestHandlerInterface
{
    public function __construct(
        public WorkloadRepository $workload
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(collect($this->workload->get())->sortBy('name')->values()->toArray());
    }
}
