<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware;


use Hyperf\Dispatcher\Exceptions\InvalidArgumentException;
use Hyperf\Dispatcher\HttpRequestHandler as HyperfRequestHandler;
use Psr\Http\Message\ResponseInterface;

class HttpRequestHandler extends HyperfRequestHandler
{
    protected function handleRequest($request) :ResponseInterface
    {
        if (! isset($this->middlewares[$this->offset]) && ! empty($this->coreHandler)) {
            $handler = $this->coreHandler;
            return $handler->process($request,$this->next());
        }
        /** @var $middleware Middleware */
        $middleware = $this->middlewares[$this->offset];
        $handler = $this->container->get($middleware->middleware);
        if (! method_exists($handler, 'process')) {
            throw new InvalidArgumentException('Invalid middleware, it has to provide a process() method.');
        }
        if($middleware->parameters) {
            return $handler->process($request,$this->next(),...$middleware->parameters);
        }
        return $handler->process($request,$this->next());
    }
}