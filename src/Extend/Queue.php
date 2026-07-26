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
    private ?string $config = null;

    /** @var array<string, array<string, mixed>> */
    private array $connections = [];

    public function extend(Container $container, ?Extension $extension = null): void
    {
        /** @var Repository $repository */
        $repository = $container->make(Repository::class);

        if ($path = $this->config) {
            $config = include $path;
            // Keys already present (from other extenders or core) win, but
            // keys only defined in the config file must still be applied.
            $repository->set('queue', array_merge($config, $repository->get('queue', [])));
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

    /**
     * @param array<string, mixed> $config
     */
    public function addConnection(string $name, array $config): self
    {
        $this->connections[$name] = $config;

        return $this;
    }
}
