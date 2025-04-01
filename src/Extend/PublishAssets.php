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
use Flarum\Extend\LifecycleInterface;
use Flarum\Extension\Extension;
use Flarum\Foundation\Paths;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory;

class PublishAssets implements LifecycleInterface, ExtenderInterface
{
    protected function resolveDisk(Container $container): Cloud
    {
        return $container->make(Factory::class)->disk('flarum-assets');
    }

    public function onEnable(Container $container, Extension $extension)
    {
        $from = $container->make(Paths::class)->vendor.'/laravel/horizon/public';

        if ($extension->name === 'blomstra/horizon') {
            /** @var \Illuminate\Filesystem\Filesystem $localFilesystem */
            $localFilesystem = $container->make('files');

            foreach ($localFilesystem->allFiles($from) as $file) {
                /** @var \Symfony\Component\Finder\SplFileInfo $file */
                $this->resolveDisk($container)->put('horizon/'.$file->getRelativePathname(), $file->getContents());
            }
        }
    }

    public function onDisable(Container $container, Extension $extension)
    {
        if ($extension->name === 'blomstra/horizon') {
            $this->resolveDisk($container)->deleteDirectory('horizon');
        }
    }

    public function extend(Container $container, ?Extension $extension = null)
    {
        // TODO: Implement extend() method.
    }
}
