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

namespace FoF\Horizon\Listeners;

use Flarum\Extension\Event\Disabled;
use Flarum\Extension\Event\Enabled;
use FoF\Horizon\RestartMaster;
use Illuminate\Contracts\Events\Dispatcher;

/**
 * Restart the Horizon master when an extension is enabled or disabled.
 *
 * Enabling or disabling an extension can change the supervisor *layout* — which
 * queues exist and which profiles serve them (see
 * {@see \FoF\Horizon\BuiltInRouting}, and any third-party extension that
 * configures Horizon via the extender). The master assembles that layout once
 * at boot and does not re-read it, so without this the new/removed queue only
 * takes effect after a manual `horizon:terminate`.
 *
 * Core already restarts the queue *workers* on these same events (via
 * Flarum\Queue\QueueRestarter and the illuminate:queue:restart signal), but that
 * signal does not make the Horizon master re-provision — only a master restart
 * does. We mirror core's behaviour at the master level.
 *
 * The terminate is graceful (in-flight jobs finish) and broadcast via redis, so
 * it reaches a master running in another container. We restart on any toggle
 * rather than a fixed list of "routing" extensions, because routing is
 * open-ended (extensions register their own via the extender) and this matches
 * how core already treats every toggle.
 */
class RestartOnExtensionToggle
{
    public function __construct(
        protected RestartMaster $restarter
    ) {
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen([Enabled::class, Disabled::class], $this->handle(...));
    }

    public function handle(): void
    {
        $this->restarter->broadcast();
    }
}
