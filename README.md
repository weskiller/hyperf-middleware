# 介绍
[Hyperf](https://github.com/hyperf/hyperf) 组件，支持中间件传参, 编排。

# 注意

### 框架核心组件被替换
* 通过继承类的方式替换了hyperf/http-server，hyperf/Router 组件的核心实现。可能存在副作用。

### 中间件不兼容
* 不兼容 Hyperf\HttpServer\Annotation\Middleware，需要使用 Weskiller\HyperfMiddleware\Middleware 替换
* [Hyperf](https://github.com/hyperf/hyperf) 会将同一个路由上重复中间件去重，在带参情况下，这一策略不适用。

### 中间件编排
* 编排策略对全局中间件生效
* 对于未定义的路由，全局中间件依旧有效

### 路由
* 将强制使用 `/` 分割路由


# 使用

### 配置
* 修改 config/autoload/server.php 文件

```php
<?php
    
return [
    'mode' => SWOOLE_PROCESS,
    'servers' => [
        [
            /**
            *   
            */
            'callbacks' => [
                Event::ON_REQUEST => [Weskiller\HyperfMiddleware\Http\Server::class, 'onRequest'],
            ],
        ],
    ],
    'settings' => [
        /**
        *   
        */
    ],
    'callbacks' => [
        /**
        *   
        */
    ],
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
            Weskiller\HyperfMiddleware\Middleware\Collector::class,
        ]
    ],
```
### 使用路由配置文件定义

```php
<?php

    use App\Controller\DeveloperController;
    use App\Middleware\CommonMiddleware;
    use App\Middleware\NeedParametersMiddleware;
    use App\Middleware\NeedTwoParameterMiddleware;
    use Hyperf\HttpServer\Router\Router;
    use Weskiller\HyperfMiddleware\Middleware\Middleware;
    use Weskiller\HyperfMiddleware\Middleware\Direct;
    use Weskiller\HyperfMiddleware\Middleware\Exclude;
    use Weskiller\HyperfMiddleware\Middleware\Expect;
    
    Router::get(
        '/debug',
        [DeveloperController::class,'index'],
        [
            'middleware' => [
                CommonMiddleware::class,                                                        //default
                [NeedParametersMiddleware::class,'parameters'],                                 //with parameters
                new Middleware(NeedTwoParameterMiddleware::class,'parameter1','parameter2'),    //use instance
            ],
            Exclude::Option => [
                CommonMiddleware::class                                                         //exclude 
            ],
            Expect::Option => [
                NeedParametersMiddleware::class                                                 //expect 
            ],
            Direct::Option => ture                                                              //skip all middlewares
       ],
       );
```

### 使用注解定义

```php
<?php
    
    namespace App\Controller;
    
    use App\Middleware\CommonMiddleware;
    use App\Middleware\NeedParametersMiddleware;
    use App\Middleware\NeedTwoParameterMiddleware;
    use Hyperf\HttpServer\Annotation\Controller;
    use Weskiller\HyperfMiddleware\Middleware\Middleware;
    use Weskiller\HyperfMiddleware\Middleware\Direct;
    use Weskiller\HyperfMiddleware\Middleware\Exclude;
    use Weskiller\HyperfMiddleware\Middleware\Expect;

    #[Controller(prefix='developer')]
    class DeveloperController
    {
        #[RequestMapping(method:["GET"],value: 'debug')]
        #[Middleware(CommonMiddleware::class)]                                  //default
        #[ParameterMiddleware(NeedParametersMiddleware::class,"parameter")]     //with parameters
        #[ParameterMiddleware(NeedTwoParameterMiddleware::class,"parameter")]   //multiple middleware
        #[Exclude([NeedTwoParameterMiddleware::class])]                         //exclude
        #[Expect([NeedParametersMiddleware::class])]                            //expect
        #[Direct]                                                               //skip all middlewares
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