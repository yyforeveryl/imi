<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Event\Listener;

use Imi\Swoole\Server\Event\Param\WorkerErrorEventParam;

/**
 * 监听服务器workererror事件接口.
 */
interface IWorkerErrorEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(WorkerErrorEventParam $e): void;
}
