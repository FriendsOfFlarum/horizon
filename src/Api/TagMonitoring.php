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

use Illuminate\Support\Collection;
use Laminas\Diactoros\Response\JsonResponse;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TagMonitoring implements RequestHandlerInterface
{
    /**
     * @var TagRepository
     */
    private $tags;
    /**
     * @var JobRepository
     */
    private $jobs;

    public function __construct(TagRepository $tags, JobRepository $jobs)
    {
        $this->tags = $tags;
        $this->jobs = $jobs;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tag = $request->getQueryParams()['tag'];

        $jobIds = $this->tags->paginate(
            $tag,
            $startingAt = $request->getQueryParams()['starting_at'] ?? 0,
            $request->getQueryParams()['limit'] ?? 25
        );

        return new JsonResponse([
            'jobs'  => $this->getJobs($jobIds, $startingAt),
            'total' => $this->tags->count($tag),
        ]);
    }

    /**
     * Get the jobs for the given IDs.
     *
     * @param array $jobIds
     * @param int   $startingAt
     *
     * @return Collection
     */
    protected function getJobs($jobIds, $startingAt = 0)
    {
        return $this->jobs->getJobs($jobIds, $startingAt)->map(function ($job) {
            $job->payload = json_decode($job->payload);

            return $job;
        })->values();
    }
}
