<?php

declare(strict_types=1);

namespace Imi\Swoole\Process\Listener;

use Imi\App;
use Imi\Bean\Annotation\Listener;
use Imi\Event\EventParam;
use Imi\Event\IEventListener;
use Imi\Server\ServerManager;
use Imi\Swoole\Process\ProcessManager;
use Imi\Swoole\Server\Contract\ISwooleServer;

/**
 * @Listener(eventName="IMI.SERVERS.CREATE.AFTER",priority=Imi\Util\ImiPriority::IMI_MIN)
 * @Listener(eventName="IMI.CO_SERVER.START",priority=Imi\Util\ImiPriority::IMI_MIN)
 */
class OnServerCreateAfter implements IEventListener
{
    /**
     * 事件处理方法.
     */
    public function handle(EventParam $e): void
    {
        $servers = ServerManager::getServers();
        $server = reset($servers);
        if (!$server instanceof ISwooleServer)
        {
            return;
        }
        foreach (App::getBean('AutoRunProcessManager')->getProcesses() as $k => $process)
        {
            if (\is_array($process))
            {
                ProcessManager::runWithManager($process['process'], $process['args'] ?? [], null, null, $k);
            }
            else
            {
                ProcessManager::runWithManager($process);
            }
        }
    }
}
