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

trait RetrievesRedisInfo
{
    private function getInfo(): ?array
    {
        try {
            $connection = $this->redis->connection();

            return $connection->info();
        } catch (\Exception $e) {
            // Catch the exception if Redis is not configured or unavailable
            return [
                'error' => 'Redis connection failed: '.$e->getMessage(),
            ];
        }
    }
}
