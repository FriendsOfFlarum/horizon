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
use Laravel\Horizon\SupervisorCommands\Pause;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Replaces Laravel's horizon:pause, which sends SIGUSR2 to locally-discovered
 * PIDs via posix_kill — a no-op when the command runs in a different container
 * than the master supervisor (a standard Docker deployment). The pause is
 * broadcast through Horizon's redis command queue, which every master polls
 * each loop wherever it runs; the master is Pausable and cascades the pause to
 * its supervisors. Mirrors the cross-container fix already applied to
 * horizon:terminate.
 */
#[AsCommand(name: 'horizon:pause')]
class PauseCommand extends Command
{
    protected $signature = 'horizon:pause';

    protected $description = 'Pause the master supervisor';

    public function handle(MasterSupervisorRepository $masters, HorizonCommandQueue $queue): void
    {
        $names = collect($masters->all())->pluck('name');

        if ($names->isEmpty()) {
            $this->components->info('No master supervisors are running.');

            return;
        }

        foreach ($names as $name) {
            $queue->push(MasterSupervisor::commandQueueFor($name), Pause::class);
        }

        $this->components->info('Broadcast pause to '.$names->count().' master supervisor(s) via redis.');
    }
}
