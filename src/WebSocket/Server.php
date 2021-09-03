<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\WebSocket;

use Hyperf\Contract\ConfigInterface;
use Hyperf\WebSocketServer\Exception\Handler\WebSocketExceptionHandler;
use Hyperf\WebSocketServer\Server as WebSocketServer;
use Weskiller\HyperfMiddleware\Middleware\Collector;

class Server extends WebSocketServer
{
    public function initCoreMiddleware(string $serverName): void
    {
        $this->serverName = $serverName;
        $this->coreMiddleware = new CoreMiddleware($this->container, $serverName);

        $this->middlewares = Collector::getGlobalMiddlewares($serverName);
        $config = $this->container->get(ConfigInterface::class);
        $this->exceptionHandlers = $config->get('exceptions.handler.' . $serverName, $this->getDefaultExceptionHandler());
    }

    protected function getDefaultExceptionHandler(): array
    {
        return [
            WebSocketExceptionHandler::class,
        ];
    }
}