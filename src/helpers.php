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

use Illuminate\Contracts\Config\Repository;

if (!function_exists('config')) {
    /**
     * @param mixed $default
     *
     * @return mixed
     */
    function config(string $key, $default = null)
    {
        /** @var Repository $config */
        $config = resolve(Repository::class);

        return $config->get($key, $default);
    }
}
