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

use FoF\Horizon\RestartMaster;
use Illuminate\Console\Command;
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

    public function handle(RestartMaster $restarter): void
    {
        $count = $restarter->broadcast();

        if ($count === 0) {
            $this->components->info('No master supervisors are running.');

            return;
        }

        $this->components->info('Broadcast terminate to '.$count.' master supervisor(s) via redis.');
    }
}
