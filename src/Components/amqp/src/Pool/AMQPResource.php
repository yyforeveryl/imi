<?php

declare(strict_types=1);

namespace Imi\AMQP\Pool;

use Imi\Pool\BasePoolResource;
use Imi\Swoole\Util\Coroutine;
use PhpAmqpLib\Connection\AbstractConnection;

/**
 * AMQP 客户端连接池的资源.
 */
class AMQPResource extends BasePoolResource
{
    /**
     * AMQP 客户端.
     */
    private AbstractConnection $connection;

    public function __construct(\Imi\Pool\Interfaces\IPool $pool, AbstractConnection $connection)
    {
        parent::__construct($pool);
        $this->connection = $connection;
    }

    /**
     * 打开
     */
    public function open(): bool
    {
        if (!$this->connection->isConnected())
        {
            $this->connection->reconnect();
        }

        return $this->connection->isConnected();
    }

    /**
     * 关闭.
     */
    public function close(): void
    {
        if (Coroutine::isIn())
        {
            $this->connection->close();
        }
        $this->connection->getIO()->close();
    }

    /**
     * 获取对象实例.
     *
     * @return \PhpAmqpLib\Connection\AbstractConnection
     */
    public function getInstance()
    {
        return $this->connection;
    }

    /**
     * 重置资源，当资源被使用后重置一些默认的设置.
     */
    public function reset(): void
    {
        foreach ($this->connection->channels as $key => $channel)
        {
            if (0 === $key)
            {
                continue;
            }
            try
            {
                $channel->close();
                unset($this->connection->channels[$key]);
            }
            catch (\Exception $e)
            {
                /* Ignore closing errors */
            }
        }
    }

    /**
     * 检查资源是否可用.
     */
    public function checkState(): bool
    {
        return $this->connection->isConnected();
    }
}
