<?php

declare(strict_types=1);


namespace Weskiller\HyperfMiddleware\Http;

use Hyperf\Contract\ConfigInterface;
use Hyperf\ExceptionHandler\ExceptionHandlerDispatcher;
use Hyperf\HttpServer\Contract\CoreMiddlewareInterface;
use Hyperf\HttpServer\ResponseEmitter;
use Hyperf\HttpServer\Router\Dispatched;
use Hyperf\HttpServer\Server as HyperfServer;
use Hyperf\Utils\Coordinator\Constants;
use Hyperf\Utils\Coordinator\CoordinatorManager;
use Psr\Container\ContainerInterface;

use Throwable;
use Weskiller\HyperfMiddleware\Middleware\Collector;

class Server extends HyperfServer
{
    public function __construct(
        ContainerInterface $container,
        Dispatcher $dispatcher,
        ExceptionHandlerDispatcher $exceptionHandlerDispatcher,
        ResponseEmitter $responseEmitter
    ) {
        parent::__construct($container, $dispatcher, $exceptionHandlerDispatcher, $responseEmitter);
    }

    public function initCoreMiddleware(string $serverName): void
    {
        $this->serverName = $serverName;
        $this->coreMiddleware = $this->createCoreMiddleware();
        $this->routerDispatcher = $this->createDispatcher($serverName);

        $this->middlewares = Collector::getGlobalMiddlewares($serverName);
        $config = $this->container->get(ConfigInterface::class);
        $this->exceptionHandlers = $config->get('exceptions.handler.' . $serverName, $this->getDefaultExceptionHandler());
    }

    protected function createDispatcher(string $serverName): \FastRoute\Dispatcher
    {
        $factory = $this->container->get(DispatcherFactory::class);
        return $factory->getDispatcher($serverName);
    }

    protected function createCoreMiddleware(): CoreMiddlewareInterface
    {
        return make(CoreMiddleware::class, [$this->container, $this->serverName]);
    }


    public function onRequest($request, $response): void
    {
        try {
            CoordinatorManager::until(Constants::WORKER_START)->yield();

            [$psr7Request] = $this->initRequestAndResponse($request, $response);

            $psr7Request = $this->coreMiddleware->dispatch($psr7Request);
            /** @var Dispatched $dispatched */
            $dispatched = $psr7Request->getAttribute(Dispatched::class);
            $middlewares = $this->middlewares;
            if ($dispatched->isFound()) {
                $middlewares = Collector::getRouteMiddlewares($this->serverName,$dispatched->handler->route, $psr7Request->getMethod());
            }

            $psr7Response = $this->dispatcher->dispatch($psr7Request, $middlewares, $this->coreMiddleware);
        } catch (Throwable $throwable) {
            // Delegate the exception to exception handler.
            $psr7Response = $this->exceptionHandlerDispatcher->dispatch($throwable, $this->exceptionHandlers);
        } finally {
            // Send the Response to client.
            if (! isset($psr7Response)) {
                return;
            }
            if (isset($psr7Request) && $psr7Request->getMethod() === 'HEAD') {
                $this->responseEmitter->emit($psr7Response, $response, false);
            } else {
                $this->responseEmitter->emit($psr7Response, $response, true);
            }
        }
    }
}