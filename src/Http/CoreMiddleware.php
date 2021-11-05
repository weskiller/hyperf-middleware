<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Http;

use FastRoute\Dispatcher;
use Hyperf\HttpServer\CoreMiddleware as HttpCoreMiddleware;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;


class CoreMiddleware extends HttpCoreMiddleware
{
    public function dispatch(ServerRequestInterface $request): ServerRequestInterface
    {
        $serverRequest = parent::dispatch($request);
        //allow Hyperf\HttpMessage\Server\Request instead of Hyperf\HttpMessage\Server\Request pass to middleware
        return $request instanceof Request ? $request : $serverRequest;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return parent::process($request instanceof Request ? $request->getRequest() : $request,$handler);
    }

    protected function createDispatcher(string $serverName): Dispatcher
    {
        $factory = $this->container->get(DispatcherFactory::class);
        return $factory->getDispatcher($serverName);
    }
    
    public static function staticPrepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (str_contains($handler, '@')) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new RuntimeException('Handler not exist.');
    }
}