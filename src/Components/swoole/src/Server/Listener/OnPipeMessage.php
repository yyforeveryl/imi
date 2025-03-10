<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Listener;

use Imi\Bean\Annotation\Listener;
use Imi\Event\Event;
use Imi\Swoole\Server\Event\Listener\IPipeMessageEventListener;
use Imi\Swoole\Server\Event\Param\PipeMessageEventParam;

/**
 * @Listener("IMI.MAIN_SERVER.PIPE_MESSAGE")
 */
class OnPipeMessage implements IPipeMessageEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(PipeMessageEventParam $e): void
    {
        $data = json_decode($e->message, true);
        $action = $data['action'] ?? null;
        if (!$action)
        {
            return;
        }
        Event::trigger('IMI.PIPE_MESSAGE.' . $action, [
            'workerId'  => $e->workerId,
            'data'      => $data,
        ]);
    }
}
