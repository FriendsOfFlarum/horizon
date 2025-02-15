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

use Laminas\Diactoros\Response\EmptyResponse;
use Laravel\Horizon\Jobs\RetryFailedJob;
use Laravel\Horizon\RedisQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RetryJob implements RequestHandlerInterface
{
    public function __construct(
        public RedisQueue $queue
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getParsedBody()['id'];

        $this->queue->push(new RetryFailedJob($id));

        return new EmptyResponse();
    }
}
