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

use Laravel\Horizon\Console\PauseCommand as BasePauseCommand;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\Pause;

// The base command's handle() signature is fixed at (MasterSupervisorRepository
// $masters), so the command queue is resolved from the container rather than
// injected as a second parameter — overriding with an extra argument would
// break signature compatibility with the parent.

/**
 * Laravel's horizon:pause sends SIGUSR2 to locally-discovered PIDs via
 * posix_kill, which silently does nothing when the command runs in a
 * different container than the master supervisor (a standard split
 * web/worker Docker deployment) — it reports "No processes to pause" and the
 * running master is unaffected.
 *
 * We inherit the command's identity ($signature/$description/name) from the
 * base command and replace only handle(): the pause is broadcast through
 * Horizon's redis command queue, which every master polls each loop wherever
 * it runs. The master is Pausable and cascades pause() to its supervisors.
 * Mirrors the cross-container fix applied to horizon:terminate.
 */
class PauseCommand extends BasePauseCommand
{
    public function handle(MasterSupervisorRepository $masters): void
    {
        $names = collect($masters->all())->pluck('name');

        if ($names->isEmpty()) {
            $this->components->info('No master supervisors are running.');

            return;
        }

        $queue = $this->laravel->make(HorizonCommandQueue::class);

        foreach ($names as $name) {
            $queue->push(MasterSupervisor::commandQueueFor($name), Pause::class);
        }

        $this->components->info('Broadcast pause to '.$names->count().' master supervisor(s) via redis.');
    }
}
