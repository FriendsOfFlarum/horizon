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

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\HorizonCommandQueue;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\MasterSupervisor;
use Laravel\Horizon\SupervisorCommands\ContinueWorking;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Replaces Laravel's horizon:continue — see PauseCommand for why the stock
 * command's local posix_kill approach fails across containers.
 */
#[AsCommand(name: 'horizon:continue')]
class ContinueCommand extends Command
{
    protected $signature = 'horizon:continue';

    protected $description = 'Instruct the master supervisor to continue working';

    public function handle(MasterSupervisorRepository $masters, HorizonCommandQueue $queue): void
    {
        $names = collect($masters->all())->pluck('name');

        if ($names->isEmpty()) {
            $this->components->info('No master supervisors are running.');

            return;
        }

        foreach ($names as $name) {
            $queue->push(MasterSupervisor::commandQueueFor($name), ContinueWorking::class);
        }

        $this->components->info('Broadcast continue to '.$names->count().' master supervisor(s) via redis.');
    }
}
