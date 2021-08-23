<?php

declare(strict_types=1);

namespace Weskiller\HyperfMiddleware\Http;

use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\Dispatcher;
use FastRoute\RouteParser\Std;
use Hyperf\Di\Annotation\MultipleAnnotationInterface;
use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Annotation\PatchMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Router\DispatcherFactory as HttpDispatcherFactory;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use ReflectionMethod;
use Weskiller\HyperfMiddleware\Route\Collector;
use Weskiller\HyperfMiddleware\Middleware\Collector as MiddlewareCollector;
use Weskiller\HyperfMiddleware\Middleware\Middleware;

class DispatcherFactory extends HttpDispatcherFactory
{

    /** @var Collector[] */
    protected $routers;

    public function getRouter(string $serverName): Collector
    {
        if (isset($this->routers[$serverName])) {
            return $this->routers[$serverName];
        }

        $parser = new Std();
        $generator = new DataGenerator();
        return $this->routers[$serverName] = new Collector($parser, $generator, $serverName);
    }

    /**
     * @throws ConflictAnnotationException
     */
    protected function initAnnotationRoute(array $collector): void
    {
        foreach ($collector as $className => $metadata) {
            $middlewares = MiddlewareCollector::getClassMiddlewares($className);
            if (isset($metadata['_c'][AutoController::class])) {
                if ($this->hasControllerAnnotation($metadata['_c'])) {
                    $message = sprintf('AutoController annotation can\'t use with Controller annotation at the same time in %s.', $className);
                    throw new ConflictAnnotationException($message);
                }
                $this->handleAutoController($className, $metadata['_c'][AutoController::class], $metadata['_m'] ?? []);
            }
            if (isset($metadata['_c'][Controller::class])) {
                $this->handleController($className, $metadata['_c'][Controller::class], $metadata['_m'] ?? []);
            }
        }
    }

    /**
     * Register route according to AutoController annotation.
     *
     */
    protected function handleAutoController(string $className, AutoController $annotation, array $methodMetadata = [], array $deprecated = []): void
    {
        $class = ReflectionManager::reflectClass($className);
        $methods = $class->getMethods(ReflectionMethod::IS_PUBLIC);
        $prefix = $this->getPrefix($className, $annotation->prefix);
        $router = $this->getRouter($annotation->server);

        $autoMethods = ['GET', 'POST', 'HEAD'];
        $defaultAction = '/index';
        foreach ($methods as $method) {
            $methodName = $method->getName();
            if ($this->isMagicMethod($methodName)) {
                continue;
            }
            $options = $annotation->options;
            $path = $this->parsePath($prefix, $method);
            $router->addRoute($autoMethods, $path, [$className, $methodName], $options);

            if (Str::endsWith($path, $defaultAction)) {
                $path = Str::replaceLast($defaultAction, '', $path);
                $router->addRoute($autoMethods, $path, [$className, $methodName], $options);
            }
        }
    }

    /**
     * Register route according to Controller and XxxMapping annotations.
     * Including RequestMapping, GetMapping, PostMapping, PutMapping, PatchMapping, DeleteMapping.
     *
     */
    protected function handleController(string $className, Controller $annotation, array $methodMetadata, array $deprecated = []): void
    {
        if (! $methodMetadata) {
            return;
        }
        $prefix = $this->getPrefix($className, $annotation->prefix);
        $router = $this->getRouter($annotation->server);

        $mappingAnnotations = [
            RequestMapping::class,
            GetMapping::class,
            PostMapping::class,
            PutMapping::class,
            PatchMapping::class,
            DeleteMapping::class,
        ];

        foreach ($methodMetadata as $methodName => $values) {
            $annotationOptions = $annotation->options;
            foreach ($mappingAnnotations as $mappingAnnotation) {
                /** @var Mapping $mapping */
                if ($mapping = $values[$mappingAnnotation] ?? null) {
                    if (!isset($mapping->path, $mapping->methods, $mapping->options)) {
                        continue;
                    }
                    $options = Arr::merge($annotationOptions, $mapping->options);
                    $path = Collector::prefixUri($prefix,$mapping->path);
                    $router->addRoute($mapping->methods, $path, [$className, $methodName], $options);
                }
            }
        }
    }

    public function isMagicMethod(string $method) :bool
    {
        return str_starts_with($method, '__');
    }
}