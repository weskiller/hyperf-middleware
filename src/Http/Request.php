<?php

namespace Weskiller\HyperfMiddleware\Http;

use Hyperf\HttpServer\Request as ServerRequest;
use Psr\Http\Message\ServerRequestInterface;

class Request extends ServerRequest
{
    public function getRequest(): ServerRequestInterface
    {
        return parent::getRequest();
    }
}