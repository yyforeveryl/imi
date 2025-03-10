<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Event\Listener;

use Imi\Swoole\Server\Event\Param\ConnectEventParam;

/**
 * 监听服务器connect事件接口.
 */
interface IConnectEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(ConnectEventParam $e): void;
}
