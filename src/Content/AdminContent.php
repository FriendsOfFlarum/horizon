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
        $cacheInfo = $this->getCacheInfo();
        $document->payload['cacheStore'] = Arr::get($cacheInfo, 'type');
        $document->payload['cacheVersion'] = Arr::get($cacheInfo, 'version');
    }

    protected function getCacheInfo(): array
    {
        $info = $this->getInfo();

        if ($error = Arr::get($info, 'error')) {
            return [
                'type'    => 'Error',
                'version' => '',
                'error'   => $error,
            ];
        }

        // Check if this is Valkey by looking for valkey_version in the info
        if ($valkeyVersion = Arr::get($info, 'Server.valkey_version')) {
            return [
                'type'    => 'Valkey',
                'version' => $valkeyVersion,
            ];
        }

        // Otherwise assume it's Redis
        return [
            'type'    => $info ? 'Redis' : 'unknown',
            'version' => Arr::get($info, 'Server.redis_version', 'unknown'),
        ];
    }
}
