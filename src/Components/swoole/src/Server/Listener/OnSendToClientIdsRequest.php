<?php

declare(strict_types=1);

namespace Imi\Swoole\Server\Listener;

use Imi\Bean\Annotation\Listener;
use Imi\Event\EventParam;
use Imi\Event\IEventListener;
use Imi\Swoole\Server\Server;
use Imi\Swoole\SwooleWorker;

/**
 * 发送给指定连接-请求
 *
 * @Listener(eventName="IMI.PIPE_MESSAGE.sendToClientIdsRequest")
 */
class OnSendToClientIdsRequest implements IEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(EventParam $e): void
    {
        $eData = $e->getData();
        $workerId = $eData['workerId'] ?? -1;
        $data = $eData['data'];
        $result = Server::sendRaw($data['data'], $data['clientIds'], $data['serverName'], false);
        if (($data['needResponse'] ?? true) && !SwooleWorker::isWorkerIdProcess($workerId))
        {
            Server::sendMessage('sendToClientIdsResponse', [
                'messageId'  => $data['messageId'],
                'result'     => $result,
                'serverName' => $data['serverName'],
            ], $workerId, $data['serverName']);
        }
    }
}
