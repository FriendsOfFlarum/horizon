<?php

/*
 * This file is part of blomstra/horizon.
 *
 * Copyright (c) Bokt.
 * Copyright (c) Blomstra Ltd.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace FoF\Horizon\Dispatcher;

use Illuminate\Contracts\Notifications\Dispatcher;

class Notifier implements Dispatcher
{
    /**
     * Send the given notification to the given notifiable entities.
     *
     * @param \Illuminate\Support\Collection|array|mixed $notifiables
     * @param mixed                                      $notification
     *
     * @return void
     */
    public function send($notifiables, $notification)
    {
        // TODO: Implement send() method.
    }

    /**
     * Send the given notification immediately.
     *
     * @param \Illuminate\Support\Collection|array|mixed $notifiables
     * @param mixed                                      $notification
     *
     * @return void
     */
    public function sendNow($notifiables, $notification)
    {
        // TODO: Implement sendNow() method.
    }
}
