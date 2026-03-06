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
    private function getInfo(): array
    {
        try {
            $connection = $this->redis->connection();
            $info = $connection->info();

            // Predis returns a nested array keyed by section name (e.g. ['Memory' => [...]]).
            // phpredis returns a flat array. Normalise the flat format into nested sections
            // so that Arr::get($info, 'Memory.maxmemory_policy') works with both drivers.
            if (!isset($info['Memory']) || !is_array($info['Memory'])) {
                $sections = ['server', 'clients', 'memory', 'stats', 'replication', 'cpu', 'keyspace'];
                $nested = [];

                foreach ($sections as $section) {
                    $sectionData = $connection->info($section);
                    if (is_array($sectionData) && !empty($sectionData)) {
                        $nested[ucfirst($section)] = $sectionData;
                    }
                }

                $info = $nested;
            }

            return $info;
        } catch (\Exception $e) {
            // Catch the exception if Redis is not configured or unavailable
            return [
                'error' => 'Redis connection failed: '.$e->getMessage(),
            ];
        }
    }
}
