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

namespace FoF\Horizon\Content;

use Flarum\Frontend\Document;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class AdminContent
{
    /**
     * @var RedisManager
     */
    protected $redis;

    public function __construct(RedisManager $redis)
    {
        $this->redis = $redis;
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $document->payload['redisVersion'] = $this->getRedisVersion();
    }

    private function getInfo(): array
    {
        return $this->redis->connection()->info();
    }

    protected function getRedisVersion(): string
    {
        return Arr::get($this->getInfo(), 'Server.redis_version', 'unknown');
    }
}
