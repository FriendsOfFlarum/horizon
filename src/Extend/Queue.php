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

namespace FoF\Horizon\Extend;

use Flarum\Extend\ExtenderInterface;
use Flarum\Extension\Extension;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;

class Queue implements ExtenderInterface
{
    private $config;
    private $connections = [];

    public function extend(Container $container, Extension $extension = null)
    {
        /** @var Repository $repository */
        $repository = $container->make(Repository::class);

        if ($path = $this->config) {
            $config = include $path;
            $repository->set('queue', array_merge($repository->get('queue', $config)));
        }

        foreach ($this->connections as $name => $config) {
            $repository->set('queue.connections.'.$name, $config);
        }
    }

    /**
     * Use a configuration file to configure the Queue.
     *
     * @param string $path
     *
     * @return Queue
     */
    public function config(string $path)
    {
        $this->config = $path;

        return $this;
    }

    public function addConnection(string $name, array $config)
    {
        $this->connections[$name] = $config;

        return $this;
    }
}
