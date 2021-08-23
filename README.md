# 介绍
[Hyperf](https://github.com/hyperf/hyperf) 组件，支持中间件传参

# 注意

### 中间件可以重复执行
[Hyperf](https://github.com/hyperf/hyperf) 会将同一个路由上重复中间件去重，在带参情况下，这一策略不适用。
### 核心实现被替换
本组件通过 dependencies.php 替代了hyperf/http-server, hyperf/router 核心的实现。可能存在副作用。

# 使用

### 配置
* 修改 dependencies.php 文件, 替换框架组件

```php
<?php
    
    return [
        Hyperf\HttpServer\Router\DispatcherFactory::class =>  \Weskiller\HyperfMiddleware\Http\DispatcherFactory::class,
        Hyperf\Dispatcher\HttpDispatcher::class => \Weskiller\HyperfMiddleware\Http\Dispatcher::class,
    ];
```

* 修改 annotations.php 文件, 新增中间件收集器

```php
<?php
    'scan' => [
        /*
        ...
        */
        'collectors' => [
            \Weskiller\HyperfMiddleware\Middleware\Collector::class,
        ]
    ],
```
### 使用路由配置文件定义

```php
<?php

    use App\Controller\DeveloperController;use App\Middleware\CommonMiddleware;use App\Middleware\NeedParametersMiddleware;use App\Middleware\NeedTwoParameterMiddleware;use Hyperf\HttpServer\Router\Router;use Weskiller\HyperfMiddleware\Middleware\Middleware;

    Router::get('/debug',[DeveloperController::class,'index'],['middleware' => [
        CommonMiddleware::class,                                                        //默认
        [NeedParametersMiddleware::class,'parameters'],                                 //带参数组
        new Middleware(NeedTwoParameterMiddleware::class,'parameter1','parameter2'),    //中间件实例
    ]]);
```

### 使用注解定义

```php
<?php
    
    namespace App\Controller;
    
    use App\Middleware\CommonMiddleware;use App\Middleware\NeedParametersMiddleware;use App\Middleware\NeedTwoParameterMiddleware;use Hyperf\HttpServer\Annotation\Controller;use Hyperf\HttpServer\Annotation\Middleware;use Weskiller\HyperfMiddleware\Middleware\Middleware as ParameterMiddleware;

    /**
     * @Controller(prefix="/")
     */
    class DeveloperController
    {
        #[RequestMapping(method:["GET"],value: 'debug')]
        #[Middleware(CommonMiddleware::class)]
        #[ParameterMiddleware(NeedParametersMiddleware::class,"parameter")] //可以和原注解混用，原注解优先级更高（先进后出）
        public function index(ServerRequestInterface $request)
        {
            $user = $request->input('user', 'Hyperf');
            $method = $request->getMethod();
    
            return [
                'method' => $method,
                'message' => "Hello {$user}.",
            ];
        }
    }
```