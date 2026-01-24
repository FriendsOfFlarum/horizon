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

namespace FoF\Horizon\Content;

use Flarum\Frontend\Document;
use FoF\Horizon\Traits\RetrievesRedisInfo;
use FoF\Redis\Overrides\RedisManager;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class AdminContent
{
    use RetrievesRedisInfo;

    public function __construct(
        protected RedisManager $redis
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        $cacheInfo = $this->getCacheInfo();
        $document->payload['cacheStore'] = $cacheInfo['type'];
        $document->payload['cacheVersion'] = $cacheInfo['version'];

        $queueDriverString = Arr::get($document->payload, 'queueDriver');
        if ($queueDriverString === 'redis') {
            $document->payload['queueDriver'] = 'Redis + Horizon';
        }
    }

    protected function getCacheInfo(): array
    {
        $info = $this->getInfo();

        if (Arr::has($info, 'error')) {
            return [
                'type'    => 'error',
                'version' => Arr::get($info, 'error', 'unknown'),
            ];
        }

        // Check if this is Valkey by looking for valkey_version in the info
        if (Arr::has($info, 'Server.valkey_version')) {
            return [
                'type'    => 'Valkey',
                'version' => Arr::get($info, 'Server.valkey_version', 'unknown'),
            ];
        }

        // Otherwise assume it's Redis
        return [
            'type'    => 'Redis',
            'version' => Arr::get($info, 'Server.redis_version', 'unknown'),
        ];
    }
}
