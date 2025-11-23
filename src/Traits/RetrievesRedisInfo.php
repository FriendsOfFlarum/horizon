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

namespace FoF\Horizon\Traits;

use Exception;

trait RetrievesRedisInfo
{
    /**
     * Retrieves Redis info with error handling.
     *
     * @return array
     */
    protected function getInfo(): array
    {
        try {
            return $this->redis->connection()->info();
        } catch (Exception $e) {
            return [
                'error' => 'Redis connection failed: '.$e->getMessage(),
            ];
        }
    }
}
