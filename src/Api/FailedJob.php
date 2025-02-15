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
use Laravel\Horizon\Contracts\JobRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FailedJob implements RequestHandlerInterface
{
    /**
     * @var JobRepository
     */
    private $jobs;

    public function __construct(JobRepository $jobs)
    {
        $this->jobs = $jobs;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $id = $request->getQueryParams()['id'];

        return new JsonResponse((array) $this->jobs->getJobs([$id])->map(function ($job) {
            return $this->decode($job);
        })->first());
    }

    protected function decode($job)
    {
        $job->payload = json_decode($job->payload);

        $job->retried_by = collect(json_decode($job->retried_by))
            ->sortByDesc('retried_at')->values();

        return $job;
    }
}
