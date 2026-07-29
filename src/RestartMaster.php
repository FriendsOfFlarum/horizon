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

namespace FoF\Horizon;

use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\Terminate;

/**
 * Broadcasts a graceful terminate to every running Horizon master via its redis
 * command queue, so each restarts and re-reads its configuration on the next
 * boot.
 *
 * This is how a master picks up a changed supervisor *layout* — which profiles
 * exist and which queues they serve. Core's queue-restart signal
 * (`illuminate:queue:restart`) only recycles the worker processes; it does not
 * make the master re-provision, so a layout change (e.g. an extension being
 * enabled that routes a new queue) needs this instead.
 *
 * Broadcasting via the command queue — rather than signalling local PIDs —
 * reaches masters running in other containers or hosts, the same reason
 * {@see \FoF\Horizon\Console\TerminateCommand} uses it.
 */
class RestartMaster
{
    public function __construct(
        protected MasterSupervisorRepository $masters,
        protected HorizonCommandQueue $queue
    ) {
    }

    /**
     * @return int the number of masters signalled
     */
    public function broadcast(): int
    {
        $names = collect($this->masters->all())->pluck('name');

        foreach ($names as $name) {
            $this->queue->push(MasterSupervisor::commandQueueFor($name), Terminate::class, ['status' => 0]);
        }

        return $names->count();
    }
}
