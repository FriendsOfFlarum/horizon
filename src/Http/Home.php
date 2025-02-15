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

namespace FoF\Horizon\Http;

use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory;
use Laminas\Diactoros\Response\HtmlResponse;
use Laravel\Horizon\Horizon;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Home implements RequestHandlerInterface
{
    public function __construct(
        private Factory $view, 
        private Config $config, 
        private SettingsRepositoryInterface $settings, 
        private Paths $paths
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse($this->view->make('horizon::layout', [
            'assetsAreCurrent'             => !$this->config->inDebugMode(),
            'js'                           => $this->getFileContents('app.js'),
            'cssApp'                       => $this->getFileContents('app.css'),   
            'cssLight'                     => $this->getFileContents('styles.css'),
            'cssDark'                      => $this->getFileContents('styles-dark.css'),
            'horizonScriptVariables'       => Horizon::scriptVariables(),
            'isDownForMaintenance'         => $this->config->inMaintenanceMode(),
            'forumTitle'                   => $this->settings->get('forum_title'),
        ])->render());
    }

    protected function getFileContents(string $file): string
    {
        return @file_get_contents($this->paths->vendor.'/laravel/horizon/dist/'.$file);
    }
}
