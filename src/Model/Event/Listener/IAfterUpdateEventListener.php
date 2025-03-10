<?php

declare(strict_types=1);

namespace Imi\Model\Event\Listener;

use Imi\Model\Event\Param\AfterUpdateEventParam;

/**
 * 模型 更新后 事件监听接口.
 */
interface IAfterUpdateEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(AfterUpdateEventParam $e): void;
}
