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
     * phpredis returns a flat array from `info()`, while Predis returns a nested
     * array keyed by section name (e.g. `['Memory' => [...]]`). Stats.php uses
     * dot-notation lookups like `Arr::get($info, 'Memory.maxmemory_policy')` which
     * require the nested format. Normalise the flat phpredis output into sections.
     *
     * @return array
     */
    protected function getInfo(): array
    {
        try {
            $connection = $this->redis->connection();
            $info = $connection->info();

            // If already nested (Predis-style), return as-is.
            if (isset($info['Memory']) && is_array($info['Memory'])) {
                return $info;
            }

            // phpredis returns a flat array — rebuild into section-keyed structure
            // by fetching each section individually.
            $sections = ['server', 'clients', 'memory', 'stats', 'replication', 'cpu', 'keyspace'];
            $nested = [];

            foreach ($sections as $section) {
                $sectionData = $connection->info($section);
                if (is_array($sectionData) && !empty($sectionData)) {
                    $nested[ucfirst($section)] = $sectionData;
                }
            }

            return $nested;
        } catch (Exception $e) {
            return [
                'error' => 'Redis connection failed: '.$e->getMessage(),
            ];
        }
    }
}
