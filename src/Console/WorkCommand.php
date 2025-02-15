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

namespace FoF\Horizon\Console;

use Flarum\Foundation\Config;

class WorkCommand extends \Laravel\Horizon\Console\WorkCommand
{
    protected function downForMaintenance()
    {
        if ($this->option('force')) {
            return false;
        }

        /** @var Config $config */
        $config = $this->laravel->make(Config::class);

        return $config->inMaintenanceMode();
    }
}
