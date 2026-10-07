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
use FoF\Horizon\SettingLocks;
use Illuminate\Support\Arr;
use Psr\Http\Message\ServerRequestInterface;

class AdminContent
{
    public function __construct(
        protected SettingLocks $locks
    ) {
    }

    public function __invoke(Document $document, ServerRequestInterface $request): void
    {
        // Settings pinned by a higher-precedence layer (env / config.php /
        // extend.php). The admin UI disables these inputs and explains why.
        $document->payload['horizonLockedSettings'] = $this->locks->locks();

        $queueDriverString = Arr::get($document->payload, 'queueDriver');
        if ($queueDriverString === 'redis') {
            $document->payload['queueDriver'] = 'Redis + Horizon';
        }
    }
}
