<?php

/*
 * This file is part of blomstra/horizon.
 *
 * Copyright (c) Bokt.
 * Copyright (c) Blomstra Ltd.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon\Api;

use Illuminate\Bus\BatchRepository;
use Illuminate\Database\QueryException;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Batches implements RequestHandlerInterface
{
    public $batches;

    public function __construct(BatchRepository $batches)
    {
        $this->batches = $batches;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $batches = $this->batches->get(50, $request->getQueryParams()['starting_at'] ?? -1 ?: null);
        } catch (QueryException $e) {
            $batches = [];
        }

        return new JsonResponse([
            'batches' => $batches,
        ]);
    }
}
