<?php

declare(strict_types=1);

namespace Imi\Server\UdpServer\Route;

use Imi\Server\UdpServer\Route\Annotation\UdpRoute;

class RouteItem
{
    /**
     * 注解.
     *
     * @var \Imi\Server\UdpServer\Route\Annotation\UdpRoute
     */
    public UdpRoute $annotation;

    /**
     * 回调.
     *
     * @var callable|\Imi\Server\Route\RouteCallable
     */
    public $callable;

    /**
     * 中间件列表.
     *
     * @var array
     */
    public array $middlewares = [];

    /**
     * 其它配置项.
     *
     * @var array
     */
    public array $options = [];

    /**
     * 是否为单例控制器.
     *
     * @var bool
     */
    public bool $singleton = false;

    /**
     * @param \Imi\Server\UdpServer\Route\Annotation\UdpRoute $annotation
     * @param callable|\Imi\Server\Route\RouteCallable        $callable
     * @param array                                           $options
     */
    public function __construct(UdpRoute $annotation, $callable, array $options = [])
    {
        $this->annotation = $annotation;
        $this->callable = $callable;
        $this->options = $options;
    }
}
