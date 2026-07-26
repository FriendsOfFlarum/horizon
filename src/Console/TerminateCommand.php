<?php

/*
 * This file is part of fof/horizon.
 *
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
use Laravel\Horizon\SupervisorCommands\Terminate;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Replaces Laravel's horizon:terminate, which inspects local processes and
 * signals them by PID — that silently does nothing when the command runs in
 * a different container or on a different host than the master supervisor
 * (a standard Docker deployment). Every master polls its redis command queue
 * on each loop, so broadcasting the terminate there reaches all of them,
 * wherever they run.
 */
#[AsCommand(name: 'horizon:terminate')]
class TerminateCommand extends Command
{
    protected $signature = 'horizon:terminate';

    protected $description = 'Terminate the master supervisor so it can be restarted';

    public function handle(MasterSupervisorRepository $masters, HorizonCommandQueue $queue): void
    {
        $names = collect($masters->all())->pluck('name');

        if ($names->isEmpty()) {
            $this->components->info('No master supervisors are running.');

            return;
        }

        foreach ($names as $name) {
            $queue->push(MasterSupervisor::commandQueueFor($name), Terminate::class, ['status' => 0]);
        }

        $this->components->info('Broadcast terminate to '.$names->count().' master supervisor(s) via redis.');
    }
}
