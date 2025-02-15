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

namespace FoF\Horizon\Http;

use Flarum\Foundation\Config;
use Flarum\Foundation\Paths;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Filesystem\Cloud;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\View\Factory;
use Laminas\Diactoros\Response\HtmlResponse;
use Laravel\Horizon\Horizon;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Home implements RequestHandlerInterface
{
    /**
     * @var Factory
     */
    private $view;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var SettingsRepositoryInterface
     */
    private $settings;

    private $paths;

    public function __construct(Factory $view, Config $config, SettingsRepositoryInterface $settings, Paths $paths)
    {
        $this->view = $view;
        $this->config = $config;
        $this->settings = $settings;
        $this->paths = $paths;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new HtmlResponse($this->view->make('horizon::layout', [
            'assetsAreCurrent'             => !$this->config->inDebugMode(),
            'js'                           => $this->getFileContents('app.js'),  
            'cssLight'                     => $this->getFileContents('app.css'),
            'cssDark'                      => $this->getFileContents('app-dark.css'),
            'horizonScriptVariables'       => Horizon::scriptVariables(),
            'isDownForMaintenance'         => $this->config->inMaintenanceMode(),
            'forumTitle'                   => $this->settings->get('forum_title'),
        ])->render());
    }

    protected function getFileContents(string $file): string
    {
        return @file_get_contents($this->paths->vendor.'/laravel/horizon/public/'.$file);
    }
}
