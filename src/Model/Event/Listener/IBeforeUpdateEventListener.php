<?php

declare(strict_types=1);

namespace Imi\Model\Event\Listener;

use Imi\Model\Event\Param\BeforeUpdateEventParam;

/**
 * 模型 更新前 事件监听接口.
 */
interface IBeforeUpdateEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(BeforeUpdateEventParam $e): void;
}
