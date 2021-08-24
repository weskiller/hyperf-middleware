<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Http;

use FastRoute\Dispatcher;
use Hyperf\HttpServer\CoreMiddleware as HttpCoreMiddleware;

class CoreMiddleware extends HttpCoreMiddleware
{
    protected function createDispatcher(string $serverName): Dispatcher
    {
        $factory = $this->container->get(DispatcherFactory::class);
        return $factory->getDispatcher($serverName);
    }
    
    public static function staticPrepareHandler($handler): array
    {
        if (is_string($handler)) {
            if (strpos($handler, '@') !== false) {
                return explode('@', $handler);
            }
            return explode('::', $handler);
        }
        if (is_array($handler) && isset($handler[0], $handler[1])) {
            return $handler;
        }
        throw new \RuntimeException('Handler not exist.');
    }
}