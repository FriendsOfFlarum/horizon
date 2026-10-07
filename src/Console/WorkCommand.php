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

namespace FoF\Horizon\Console;

use Flarum\Foundation\Config;

class WorkCommand extends \Laravel\Horizon\Console\WorkCommand
{
    protected function runWorker($connection, $queue)
    {
        $this->raiseMemoryLimit();

        return parent::runWorker($connection, $queue);
    }

    /**
     * Horizon restarts a worker over its `--memory` budget only once a job has
     * finished. A job that crosses PHP's own memory_limit first is killed with
     * a fatal error instead, so the worker makes sure PHP's limit sits above
     * its budget.
     */
    protected function raiseMemoryLimit(): void
    {
        $limit = static::memoryLimitFor((int) $this->option('memory'), (string) ini_get('memory_limit'));

        if ($limit !== null) {
            ini_set('memory_limit', $limit);
        }
    }

    /**
     * Twice the budget (MB), leaving a job room for as much again before PHP
     * stops it. Never lowered: a host's higher limit, or none, stays.
     *
     * @return string|null the new memory_limit, or null to leave it as it is
     */
    protected static function memoryLimitFor(int $budget, string $current): ?string
    {
        if ($budget <= 0) {
            return null;
        }

        $currentBytes = ini_parse_quantity($current);
        $target = $budget * 2;

        if ($currentBytes < 0 || $currentBytes >= $target * 1024 * 1024) {
            return null;
        }

        return $target.'M';
    }

    protected function downForMaintenance()
    {
        if ($this->option('force')) {
            return false;
        }

        /** @var Config $config */
        $config = $this->laravel->make(Config::class);

        // Match core's queue worker semantics: the queue keeps running in low
        // and safe maintenance modes, and only pauses in high maintenance.
        return $config->inHighMaintenanceMode();
    }
}
