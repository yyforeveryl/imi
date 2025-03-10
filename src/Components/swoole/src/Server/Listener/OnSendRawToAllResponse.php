<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Listener;

use Imi\Bean\Annotation\Listener;
use Imi\Event\EventParam;
use Imi\Event\IEventListener;
use Imi\Swoole\Util\Co\ChannelContainer;

/**
 * 发送给所有 Worker 进程的连接-响应.
 *
 * @Listener(eventName="IMI.PIPE_MESSAGE.sendRawToAllResponse")
 */
class OnSendRawToAllResponse implements IEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(EventParam $e): void
    {
        $data = $e->getData()['data'];
        if (ChannelContainer::hasChannel($data['messageId']))
        {
            ChannelContainer::push($data['messageId'], $data);
        }
    }
}
