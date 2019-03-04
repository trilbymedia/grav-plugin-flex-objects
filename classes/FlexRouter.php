<?php

namespace Grav\Plugin\FlexObjects;

use Grav\Framework\Route\Route;
use Grav\Plugin\FlexObjects\Controllers\MediaController;
use Grav\Plugin\FlexObjects\Controllers\ObjectController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class FlexRouter implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $request->getAttributes();

        /** @var Route $route */
        $route = $context['route'];
        $post = $request->getParsedBody();

        $task = $post['task'] ?? $route->getParam('task');

        switch ($task) {
            case 'media.list':
            case 'media.add':
            case 'media.delete':
            case 'listmedia':
            case 'addmedia':
            case 'delmedia':
                return (new MediaController())->handle($request);
            case 'save':
            case 'create':
            case 'update':
            case 'move':
            case 'delete':
            case 'preview':
                return (new ObjectController())->handle($request);
        }

        // No handler found.
        return $handler->handle($request);
    }
}
