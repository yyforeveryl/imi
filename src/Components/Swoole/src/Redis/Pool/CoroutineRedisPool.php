<?php

declare(strict_types=1);

namespace Imi\Swoole\Redis\Pool;

use Imi\App;
use Imi\Pool\TUriResourceConfig;
use Imi\Redis\RedisHandler;
use Imi\Redis\RedisResource;
use Imi\Swoole\Pool\BaseAsyncPool;

class CoroutineRedisPool extends BaseAsyncPool
{
    use TUriResourceConfig;

    /**
     * 数据库操作类.
     *
     * @var string
     */
    protected string $handlerClass = \Redis::class;

    public function __construct(string $name, \Imi\Pool\Interfaces\IPoolConfig $config = null, $resourceConfig = null)
    {
        parent::__construct($name, $config, $resourceConfig);
        $this->initUriResourceConfig();
    }

    /**
     * 创建资源.
     *
     * @return \Imi\Redis\RedisResource
     */
    protected function createResource(): \Imi\Pool\Interfaces\IPoolResource
    {
        $config = $this->getNextResourceConfig();
        $class = $config['handlerClass'] ?? $this->handlerClass;
        $db = App::getBean(RedisHandler::class, new $class());

        return new RedisResource($this, $db, $config);
    }
}
