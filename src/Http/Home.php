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
use Flarum\Http\UrlGenerator;
use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\View\Factory;
use Laminas\Diactoros\Response\HtmlResponse;
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

    private $url;

    public function __construct(Factory $view, Config $config, SettingsRepositoryInterface $settings, UrlGenerator $url)
    {
        $this->view = $view;
        $this->config = $config;
        $this->settings = $settings;
        $this->url = $url;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Get horizon path from config repository directly to avoid any issues
        $horizonPath = $this->config->offsetGet('paths.admin').'/horizon';

        return new HtmlResponse($this->view->make('horizon::layout', [
            'assetsAreCurrent'             => true,
            'jsUrl'                        => $this->url->to('admin')->route('horizon.assets.file', ['file' => 'app.js']),
            'cssLightUrl'                  => $this->url->to('admin')->route('horizon.assets.file', ['file' => 'app.css']),
            'cssDarkUrl'                   => $this->url->to('admin')->route('horizon.assets.file', ['file' => 'app-dark.css']),
            'horizonScriptVariables'       => ['path' => $horizonPath],
            'isDownForMaintenance'         => $this->config->inMaintenanceMode(),
            'forumTitle'                   => $this->settings->get('forum_title'),
            'csrfToken'                    => $request->getAttribute('session')->token(),
        ])->render());
    }
}
