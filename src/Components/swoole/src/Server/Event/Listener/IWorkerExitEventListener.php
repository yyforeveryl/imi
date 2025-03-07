<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Event\Listener;

use Imi\Swoole\Server\Event\Param\WorkerExitEventParam;

/**
 * 监听服务器workexit事件接口.
 */
interface IWorkerExitEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(WorkerExitEventParam $e): void;
}
