<?php

/*
 * This file is part of fof/horizon.
 *
 * Copyright (c) FriendsOfFlarum
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

return [
    'default'     => 'redis',
    'connections' => [
        'redis' => [
            'driver' => 'redis',
            'queue'  => 'default',
        ],
    ],
];
