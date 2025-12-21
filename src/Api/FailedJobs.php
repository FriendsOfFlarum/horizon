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

use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\TagRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FailedJobs implements RequestHandlerInterface
{
    public function __construct(
        public JobRepository $jobs,
        public TagRepository $tags
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tag = $request->getQueryParams()['tag'] ?? null;

        $jobs = !$tag
            ? $this->paginate($request)
            : $this->paginateByTag($request, $tag);

        $total = $tag
            ? $this->tags->count('failed:'.$tag)
            : $this->jobs->countFailed();

        return new JsonResponse([
            'jobs'  => $jobs,
            'total' => $total,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function paginate(ServerRequestInterface $request): \Illuminate\Support\Collection
    {
        return $this->jobs->getFailed(Arr::get($request->getQueryParams(), 'starting_at', -1))->map(function ($job) {
            return $this->decode($job);
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    protected function paginateByTag(ServerRequestInterface $request, string $tag): \Illuminate\Support\Collection
    {
        $jobIds = $this->tags->paginate(
            'failed:'.$tag,
            Arr::get($request->getQueryParams(), 'starting_at', -1) + 1,
            50
        );

        $startingAt = Arr::get($request->getQueryParams(), 'starting_at', 0);

        return $this->jobs->getJobs($jobIds, $startingAt)->map(function ($job) {
            return $this->decode($job);
        });
    }

    protected function decode(object $job): object
    {
        $job->payload = json_decode($job->payload);

        $job->exception = mb_convert_encoding($job->exception, 'UTF-8');

        $job->context = json_decode($job->context ?? '');

        /** @var array<int, object> $retriedBy */
        $retriedBy = !is_null($job->retried_by) ? json_decode($job->retried_by) : [];
        $job->retried_by = collect($retriedBy)
            ->sortByDesc('retried_at')->values();

        return $job;
    }
}
