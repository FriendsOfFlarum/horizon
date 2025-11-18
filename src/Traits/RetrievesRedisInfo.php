<?php

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
                'error' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }
}