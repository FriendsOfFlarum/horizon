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

use Laravel\Horizon\Console\ContinueCommand as BaseContinueCommand;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\ContinueWorking;

/**
 * Replaces the body of Laravel's horizon:continue — see PauseCommand for why
 * the stock command's local posix_kill approach fails across containers. We
 * keep the base command's identity and override only handle().
 */
class ContinueCommand extends BaseContinueCommand
{
    public function handle(MasterSupervisorRepository $masters): void
    {
        $names = collect($masters->all())->pluck('name');

        if ($names->isEmpty()) {
            $this->components->info('No master supervisors are running.');

            return;
        }

        // Fixed parent signature — resolve the queue from the container rather
        // than injecting it as a parameter (see PauseCommand).
        $queue = $this->laravel->make(HorizonCommandQueue::class);

        foreach ($names as $name) {
            $queue->push(MasterSupervisor::commandQueueFor($name), ContinueWorking::class);
        }

        $this->components->info('Broadcast continue to '.$names->count().' master supervisor(s) via redis.');
    }
}
