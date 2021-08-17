<?php

declare(strict_types=1);

namespace Weskiller\HyperfMiddleware;

use FastRoute\DataGenerator\GroupCountBased as DataGenerator;
use FastRoute\RouteParser\Std;
use Hyperf\Di\Annotation\MultipleAnnotationInterface;
use Hyperf\Di\Exception\ConflictAnnotationException;
use Hyperf\Di\ReflectionManager;
use Hyperf\HttpServer\Annotation\AutoController;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\DeleteMapping;
use Hyperf\HttpServer\Annotation\GetMapping;
use Hyperf\HttpServer\Annotation\Mapping;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\PatchMapping;
use Hyperf\HttpServer\Annotation\PostMapping;
use Hyperf\HttpServer\Annotation\PutMapping;
use Hyperf\HttpServer\Annotation\RequestMapping;
use Hyperf\HttpServer\Router\DispatcherFactory as HttDispatcherFactory;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use ReflectionMethod;

class DispatcherFactory extends HttDispatcherFactory
{
    /** @var RouteCollector[] */
    protected $routers;

    public function getRouter(string $serverName): RouteCollector
    {
        if (isset($this->routers[$serverName])) {
            return $this->routers[$serverName];
        }

        $parser = new Std();
        $generator = new DataGenerator();
        return $this->routers[$serverName] = new RouteCollector($parser, $generator, $serverName);
    }

    /**
     * @throws ConflictAnnotationException
     */
    protected function initAnnotationRoute(array $collector): void
    {
        foreach ($collector as $className => $metadata) {
            if (isset($metadata['_c'][AutoController::class])) {
                if ($this->hasControllerAnnotation($metadata['_c'])) {
                    $message = sprintf('AutoController annotation can\'t use with Controller annotation at the same time in %s.', $className);
                    throw new ConflictAnnotationException($message);
                }
                $middlewares = $this->mergeClassMiddleware($className,$metadata['_c']);
                $this->handleAutoController($className, $metadata['_c'][AutoController::class], $middlewares, $metadata['_m'] ?? []);
            }
            if (isset($metadata['_c'][Controller::class])) {
                $middlewares = $this->mergeClassMiddleware($className,$metadata['_c']);
                $this->handleController($className, $metadata['_c'][Controller::class], $metadata['_m'] ?? [], $middlewares);
            }
        }
    }

    /**
     * Register route according to AutoController annotation.
     *
     */
    protected function handleAutoController(string $className, AutoController $annotation, array $middlewares = [], array $methodMetadata = []): void
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

            $options['middleware'] = array_merge($middlewares,$this->mergeMethodMiddleware($className,$methodName,$methodMetadata));

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
     * @throws ConflictAnnotationException
     */
    protected function handleController(string $className, Controller $annotation, array $methodMetadata, array $middlewares = []): void
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
            $options = $annotation->options;
            $options['middleware'] = array_merge($middlewares,$this->mergeMethodMiddleware($className,$methodName,$values));
            foreach ($mappingAnnotations as $mappingAnnotation) {
                /** @var Mapping $mapping */
                if ($mapping = $values[$mappingAnnotation] ?? null) {
                    if (!isset($mapping->path, $mapping->methods, $mapping->options)) {
                        continue;
                    }
                    $methodOptions = Arr::merge($options, $mapping->options);
                    $methodOptions['middleware'] = $options['middleware'];
                    $path = $this->getUri($prefix,$mapping->path);
                    $router->addRoute($mapping->methods, $path, [$className, $methodName], $methodOptions);
                }
            }
        }
    }

    /**
     * @throws ConflictAnnotationException
     */
    protected function mergeMethodMiddleware(string $class,string $method,array $meta) :array
    {
        return array_merge(
            $this->handleMiddleware($meta),
            MiddlewareCollector::getMethod($class,$method),
        );
    }

    /**
     * @throws ConflictAnnotationException
     */
    protected function mergeClassMiddleware(string $class,array $meta):array
    {
        return array_merge(
            $this->handleMiddleware($meta),
            MiddlewareCollector::getClass($class),
        );
    }

    /**
     * @throws ConflictAnnotationException
     */
    protected function handleMiddleware(array $metadata): array
    {
        /** @var null|Middlewares $middlewares */
        $middlewares = $metadata[Middlewares::class] ?? null;
        /** @var null|MultipleAnnotationInterface $middleware */
        $middleware = $metadata[Middleware::class] ?? null;
        if ($middleware instanceof MultipleAnnotationInterface) {
            $middleware = $middleware->toAnnotations();
        }

        if (! $middlewares && ! $middleware) {
            return [];
        }
        if ($middlewares && $middleware) {
            throw new ConflictAnnotationException('Could not use @Middlewares and @Middleware annotation at the same times at same level.');
        }

        return (array)($middlewares->middlewares ?? $middleware);
    }

    public function isMagicMethod(string $method) :bool
    {
        return str_starts_with($method, '__');
    }

    public function getUri(string $prefix,string $path) :string
    {
        if ($path === '') {
            return $prefix;
        }
        if (str_starts_with($prefix,'/')) {
            return $path;
        }
        return $prefix . '/' . $path;
    }
}