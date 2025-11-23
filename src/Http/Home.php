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
    public function __construct(
        private Factory $view,
        private Config $config,
        private SettingsRepositoryInterface $settings,
        private UrlGenerator $url
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $csrfToken = $request->getAttribute('session')->token();
        $horizonPath = $this->url->to('admin')->route('horizon.index');

        return new HtmlResponse($this->view->make('horizon::layout', [
            'isDownForMaintenance'         => $this->config->inMaintenanceMode(),
            'forumTitle'                   => $this->settings->get('forum_title'),
            'csrfToken'                    => $csrfToken,
            'horizonPath'                  => $horizonPath,
        ])->render());
    }
}
