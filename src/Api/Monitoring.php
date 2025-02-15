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
use Laravel\Horizon\Contracts\TagRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Monitoring implements RequestHandlerInterface
{
    public function __construct(
        public TagRepository $tags
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(
            collect($this->tags->monitoring())->map(function ($tag) {
                return [
                    'tag'   => $tag,
                    'count' => $this->tags->count($tag) + $this->tags->count('failed:' . $tag),
                ];
            })->sortBy('tag')->values()
        );
    }
}
