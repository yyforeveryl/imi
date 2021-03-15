<?php

declare(strict_types=1);

namespace Imi\Swoole\Server;

use Imi\App;
use Imi\Event\Event;
use Imi\RequestContext;
use Imi\Server\ConnectContext\ConnectionBinder;
use Imi\Server\DataParser\DataParser;
use Imi\Server\ServerManager;
use Imi\Swoole\Server\Contract\ISwooleServer;
use Imi\Swoole\Server\Contract\ISwooleServerUtil;
use Imi\Swoole\Server\Event\Param\PipeMessageEventParam;
use Imi\Swoole\Util\Co\ChannelContainer;
use Imi\Worker;

class ServerUtil implements ISwooleServerUtil
{
    /**
     * 发送消息给 Worker 进程，使用框架内置格式.
     *
     * 返回成功发送消息数量
     *
     * @param string         $action
     * @param array          $data
     * @param int|int[]|null $workerId
     *
     * @return int
     */
    public function sendMessage(string $action, array $data = [], $workerId = null): int
    {
        $data['action'] = $action;
        $message = json_encode($data);

        return $this->sendMessageRaw($message, $workerId);
    }

    /**
     * 发送消息给 Worker 进程.
     *
     * 返回成功发送消息数量
     *
     * @param string         $message
     * @param int|int[]|null $workerId
     *
     * @return int
     */
    public function sendMessageRaw(string $message, $workerId = null): int
    {
        if (null === $workerId)
        {
            $workerId = range(0, Worker::getWorkerNum() - 1);
        }
        /** @var ISwooleServer $server */
        $server = ServerManager::getServer('main', ISwooleServer::class);
        $swooleServer = $server->getSwooleServer();
        $success = 0;
        $currentWorkerId = Worker::getWorkerId();
        foreach ((array) $workerId as $tmpWorkerId)
        {
            if ($tmpWorkerId === $currentWorkerId)
            {
                go(function () use ($server, $currentWorkerId, $message) {
                    Event::trigger('IMI.MAIN_SERVER.PIPE_MESSAGE', [
                        'server'    => $server,
                        'workerId'  => $currentWorkerId,
                        'message'   => $message,
                    ], $server, PipeMessageEventParam::class);
                });
                ++$success;
            }
            elseif ($swooleServer->sendMessage($message, $tmpWorkerId))
            {
                ++$success;
            }
        }

        return $success;
    }

    /**
     * 发送数据给指定客户端，支持一个或多个（数组）.
     *
     * 数据将会通过处理器编码
     *
     * @param mixed          $data
     * @param int|int[]|null $fd           为 null 时，则发送给当前连接
     * @param string|null    $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool           $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function send($data, $fd = null, $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        /** @var \Imi\Server\DataParser\DataParser $dataParser */
        $dataParser = $server->getBean(DataParser::class);
        if (null === $serverName)
        {
            $serverName = $server->getName();
        }

        return $this->sendRaw($dataParser->encode($data, $serverName), $fd, $serverName, $toAllWorkers);
    }

    /**
     * 发送数据给指定标记的客户端，支持一个或多个（数组）.
     *
     * 数据将会通过处理器编码
     *
     * @param mixed                $data
     * @param string|string[]|null $flag         为 null 时，则发送给当前连接
     * @param string|null          $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool                 $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendByFlag($data, $flag = null, $serverName = null, bool $toAllWorkers = true): int
    {
        /** @var ConnectionBinder $connectionBinder */
        $connectionBinder = App::getBean('ConnectionBinder');

        if (null === $flag)
        {
            $fd = RequestContext::get('fd');
            if (!$fd)
            {
                return 0;
            }
            $fds = [$fd];
        }
        else
        {
            $fds = [];
            foreach ((array) $flag as $tmpFlag)
            {
                $fd = $connectionBinder->getFdByFlag($tmpFlag);
                if ($fd)
                {
                    $fds[] = $fd;
                }
            }
            if (!$fds)
            {
                return 0;
            }
        }

        return $this->send($data, $fds, $serverName, $toAllWorkers);
    }

    /**
     * 发送数据给指定客户端，支持一个或多个（数组）.
     *
     * @param string         $data
     * @param int|int[]|null $fd           为 null 时，则发送给当前连接
     * @param string|null    $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool           $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendRaw(string $data, $fd = null, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        $swooleServer = $server->getSwooleServer();
        if (null === $fd)
        {
            $fd = RequestContext::get('fd');
            if (!$fd)
            {
                return 0;
            }
        }
        $fds = (array) $fd;
        $success = 0;
        if ($server instanceof \Imi\Swoole\Server\WebSocket\Server)
        {
            $method = 'push';
        }
        else
        {
            $method = 'send';
        }
        if (\SWOOLE_BASE === $swooleServer->mode && $toAllWorkers && 'push' === $method)
        {
            $id = uniqid('', true);
            try
            {
                $channel = ChannelContainer::getChannel($id);
                $this->sendMessage('sendToFdsRequest', [
                    'messageId'  => $id,
                    'fds'        => $fds,
                    'data'       => $data,
                    'serverName' => $server->getName(),
                ]);
                for ($i = Worker::getWorkerNum(); $i > 0; --$i)
                {
                    $result = $channel->pop(30);
                    if (false === $result)
                    {
                        break;
                    }
                    $success += ($result['result'] ?? 0);
                }
            }
            finally
            {
                ChannelContainer::removeChannel($id);
            }
        }
        else
        {
            foreach ($fds as $tmpFd)
            {
                /** @var \Swoole\WebSocket\Server $swooleServer */
                if ('push' === $method && !$swooleServer->isEstablished($tmpFd))
                {
                    continue;
                }
                if ($swooleServer->$method($tmpFd, $data))
                {
                    ++$success;
                }
            }
        }

        return $success;
    }

    /**
     * 发送数据给指定标记的客户端，支持一个或多个（数组）.
     *
     * @param string               $data
     * @param string|string[]|null $flag         为 null 时，则发送给当前连接
     * @param string|null          $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool                 $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendRawByFlag(string $data, $flag = null, $serverName = null, bool $toAllWorkers = true): int
    {
        /** @var ConnectionBinder $connectionBinder */
        $connectionBinder = App::getBean('ConnectionBinder');

        if (null === $flag)
        {
            $fd = RequestContext::get('fd');
            if (!$fd)
            {
                return 0;
            }
            $fds = [$fd];
        }
        else
        {
            $fds = [];
            foreach ((array) $flag as $tmpFlag)
            {
                $fd = $connectionBinder->getFdByFlag($tmpFlag);
                if ($fd)
                {
                    $fds[] = $fd;
                }
            }
            if (!$fds)
            {
                return 0;
            }
        }

        return $this->sendRaw($data, $fds, $serverName, $toAllWorkers);
    }

    /**
     * 发送数据给所有客户端.
     *
     * 数据将会通过处理器编码
     *
     * @param mixed       $data
     * @param string|null $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool        $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendToAll($data, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        /** @var \Imi\Server\DataParser\DataParser $dataParser */
        $dataParser = $server->getBean(DataParser::class);

        return $this->sendRawToAll($dataParser->encode($data, $serverName), $server->getName(), $toAllWorkers);
    }

    /**
     * 发送数据给所有客户端.
     *
     * 数据原样发送
     *
     * @param string      $data
     * @param string|null $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool        $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendRawToAll(string $data, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        $swooleServer = $server->getSwooleServer();
        $success = 0;
        if ($server instanceof \Imi\Swoole\Server\WebSocket\Server)
        {
            $method = 'push';
        }
        else
        {
            $method = 'send';
        }
        if (\SWOOLE_BASE === $swooleServer->mode && $toAllWorkers && 'push' === $method)
        {
            $id = uniqid('', true);
            try
            {
                $channel = ChannelContainer::getChannel($id);
                $this->sendMessage('sendRawToAllRequest', [
                    'messageId'     => $id,
                    'data'          => $data,
                    'serverName'    => $server->getName(),
                ]);
                for ($i = Worker::getWorkerNum(); $i > 0; --$i)
                {
                    $result = $channel->pop(30);
                    if (false === $result)
                    {
                        break;
                    }
                    $success += ($result['result'] ?? 0);
                }
            }
            finally
            {
                ChannelContainer::removeChannel($id);
            }
        }
        else
        {
            foreach ($server->getSwoolePort()->connections as $fd)
            {
                /** @var \Swoole\WebSocket\Server $swooleServer */
                if ('push' === $method && !$swooleServer->isEstablished($fd))
                {
                    continue;
                }
                if ($swooleServer->$method($fd, $data))
                {
                    ++$success;
                }
            }
        }

        return $success;
    }

    /**
     * 发送数据给分组中的所有客户端，支持一个或多个（数组）.
     *
     * 数据将会通过处理器编码
     *
     * @param string|string[] $groupName
     * @param mixed           $data
     * @param string|null     $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool            $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendToGroup($groupName, $data, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        /** @var \Imi\Server\DataParser\DataParser $dataParser */
        $dataParser = $server->getBean(DataParser::class);

        return $this->sendRawToGroup($groupName, $dataParser->encode($data, $serverName), $server->getName(), $toAllWorkers);
    }

    /**
     * 发送数据给分组中的所有客户端，支持一个或多个（数组）.
     *
     * 数据原样发送
     *
     * @param string|string[] $groupName
     * @param string          $data
     * @param string|null     $serverName   服务器名，默认为当前服务器或主服务器
     * @param bool            $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function sendRawToGroup($groupName, string $data, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        $swooleServer = $server->getSwooleServer();
        $groups = (array) $groupName;
        $success = 0;
        if ($server instanceof \Imi\Swoole\Server\WebSocket\Server)
        {
            $method = 'push';
        }
        else
        {
            $method = 'send';
        }
        if (\SWOOLE_BASE === $swooleServer->mode && $toAllWorkers && 'push' === $method)
        {
            $id = uniqid('', true);
            try
            {
                $channel = ChannelContainer::getChannel($id);
                $this->sendMessage('sendToGroupsRequest', [
                    'messageId'     => $id,
                    'groups'        => $groups,
                    'data'          => $data,
                    'serverName'    => $server->getName(),
                ]);
                for ($i = Worker::getWorkerNum(); $i > 0; --$i)
                {
                    $result = $channel->pop(30);
                    if (false === $result)
                    {
                        break;
                    }
                    $success += ($result['result'] ?? 0);
                }
            }
            finally
            {
                ChannelContainer::removeChannel($id);
            }
        }
        else
        {
            foreach ($groups as $tmpGroupName)
            {
                $group = $server->getGroup($tmpGroupName);
                if ($group)
                {
                    $result = $group->$method($data);
                    foreach ($result as $item)
                    {
                        if ($item)
                        {
                            ++$success;
                        }
                    }
                }
            }
        }

        return $success;
    }

    /**
     * 关闭一个或多个连接.
     *
     * @param int|int[]   $fd
     * @param string|null $serverName
     * @param bool        $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function close($fd, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        $server = $this->getServer($serverName);
        $swooleServer = $server->getSwooleServer();
        $count = 0;
        foreach ((array) $fd as $currentFd)
        {
            if ($swooleServer->close($currentFd))
            {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * 关闭一个或多个指定标记的连接.
     *
     * @param string|string[]|null $flag
     * @param string|null          $serverName
     * @param bool                 $toAllWorkers BASE模式下，发送给所有 worker 中的连接
     *
     * @return int
     */
    public function closeByFlag($flag, ?string $serverName = null, bool $toAllWorkers = true): int
    {
        /** @var ConnectionBinder $connectionBinder */
        $connectionBinder = App::getBean('ConnectionBinder');

        if (null === $flag)
        {
            $fd = RequestContext::get('fd');
            if (!$fd)
            {
                return 0;
            }
            $fds = [$fd];
        }
        else
        {
            $fds = [];
            foreach ((array) $flag as $tmpFlag)
            {
                $fd = $connectionBinder->getFdByFlag($tmpFlag);
                if ($fd)
                {
                    $fds[] = $fd;
                }
            }
            if (!$fds)
            {
                return 0;
            }
        }

        return $this->close($fds, $serverName, $toAllWorkers);
    }

    /**
     * 获取服务器.
     *
     * @param string|null $serverName
     *
     * @return \Imi\Swoole\Server\Contract\ISwooleServer|null
     */
    public function getServer(?string $serverName = null): ?ISwooleServer
    {
        if (null === $serverName)
        {
            /** @var ISwooleServer|null $server */
            $server = RequestContext::getServer();
            if ($server)
            {
                return $server;
            }
            $serverName = 'main';
        }

        // @phpstan-ignore-next-line
        return ServerManager::getServer($serverName, ISwooleServer::class);
    }
}
