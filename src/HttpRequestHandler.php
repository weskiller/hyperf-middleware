<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware;


use Hyperf\Dispatcher\Exceptions\InvalidArgumentException;
use Hyperf\Dispatcher\HttpRequestHandler as HyperfRequestHandler;
use Hyperf\HttpServer\Annotation\Middleware;
use Psr\Http\Message\ResponseInterface;

class HttpRequestHandler extends HyperfRequestHandler
{
    protected function handleRequest($request) :ResponseInterface
    {
        if (! isset($this->middlewares[$this->offset]) && ! empty($this->coreHandler)) {
            $handler = $this->coreHandler;
        } else {
            /** @var Middleware|\Weskiller\HyperfMiddleware\Middleware $middleware */
            $middleware = $this->middlewares[$this->offset];
            $handler = $middleware->middleware;
            is_string($handler) && $handler = $this->container->get($handler);
        }
        if (! method_exists($handler, 'process')) {
            throw new InvalidArgumentException('Invalid middleware, it has to provide a process() method.');
        }
        return $handler->process($request, $this->next(),... $middleware->parameters ?? []);
    }
}