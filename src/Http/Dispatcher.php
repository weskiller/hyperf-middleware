<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Http;


use Hyperf\Dispatcher\HttpDispatcher as HyperfHttpDispatcher;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;

class Dispatcher extends HyperfHttpDispatcher
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function dispatch(...$params): ResponseInterface
    {
        /**
         * @param RequestInterface $request
         * @param array $middlewares
         * @param MiddlewareInterface $coreHandler
         */
        [$request, $middlewares,$coreHandler] = $params;
        return (new RequestHandler($middlewares, $coreHandler, $this->container))->handle($request);
    }
}