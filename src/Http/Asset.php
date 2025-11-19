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

use Flarum\Foundation\Paths;
use Laminas\Diactoros\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Asset implements RequestHandlerInterface
{
    private $paths;

    public function __construct(Paths $paths)
    {
        $this->paths = $paths;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $file = $request->getQueryParams()['file'] ?? 'app.js';

        // Security: only allow specific files
        $allowedFiles = ['app.js', 'app.css', 'app-dark.css'];

        if (!in_array($file, $allowedFiles)) {
            return new Response\EmptyResponse(404);
        }

        $filePath = $this->paths->vendor.'/laravel/horizon/public/'.$file;

        if (!file_exists($filePath)) {
            return new Response\EmptyResponse(404);
        }

        $content = file_get_contents($filePath);

        // Determine MIME type
        if ($file === 'app.js') {
            $mimeType = 'application/javascript';
        } elseif (str_ends_with($file, '.css')) {
            $mimeType = 'text/css';
        } else {
            $mimeType = 'text/plain';
        }

        $response = new Response();
        $response->getBody()->write($content);

        return $response
            ->withHeader('Content-Type', $mimeType)
            ->withHeader('Cache-Control', 'public, max-age=31536000');
    }
}
