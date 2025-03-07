<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Event\Listener;

use Imi\Swoole\Server\Event\Param\WorkerStopEventParam;

/**
 * 监听服务器workerstop事件接口.
 */
interface IWorkerStopEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(WorkerStopEventParam $e): void;
}
