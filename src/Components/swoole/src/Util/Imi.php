<?php

declare(strict_types=1);

namespace Imi\Swoole\Util;

use Imi\App;
use Imi\Config;
use Imi\Util\Imi as ImiUtil;
use Imi\Worker;

class Imi
{
    private function __construct()
    {
    }

    /**
     * 设置当前进程名.
     */
    public static function setProcessName(string $type, array $data = []): void
    {
        if ('Darwin' === \PHP_OS)
        {
            // 苹果 MacOS 不允许设置进程名
            return;
        }
        cli_set_process_title(static::getProcessName($type, $data));
    }

    /**
     * 获取 imi 进程名.
     */
    public static function getProcessName(string $type, array $data = []): string
    {
        static $defaults = [
            'master'        => 'imi:master:{namespace}',
            'manager'       => 'imi:manager:{namespace}',
            'worker'        => 'imi:worker-{workerId}:{namespace}',
            'taskWorker'    => 'imi:taskWorker-{workerId}:{namespace}',
            'process'       => 'imi:process-{processName}:{namespace}',
            'processPool'   => 'imi:process-pool-{processPoolName}-{workerId}:{namespace}',
        ];
        if (!isset($defaults[$type]))
        {
            return '';
        }
        $rule = Config::get('@app.process.' . $type, $defaults[$type]);
        $data['namespace'] = App::getNamespace();
        switch ($type)
        {
            case 'master':
                break;
            case 'manager':
                break;
            case 'worker':
                $data['workerId'] = Worker::getWorkerId();
                break;
            case 'taskWorker':
                $data['workerId'] = Worker::getWorkerId();
                break;
            case 'process':
                if (!isset($data['processName']))
                {
                    return '';
                }
                break;
            case 'processPool':
                if (!isset($data['processPoolName'], $data['workerId']))
                {
                    return '';
                }
                break;
        }
        $result = $rule;
        foreach ($data as $k => $v)
        {
            if (!is_scalar($v))
            {
                continue;
            }
            $result = str_replace('{' . $k . '}', (string) $v, $result);
        }

        return $result;
    }

    /**
     * 停止服务器.
     */
    public static function stopServer(): void
    {
        $fileName = Config::get('@app.mainServer.configs.pid_file', ImiUtil::getRuntimePath('swoole.pid'));
        if (!is_file($fileName))
        {
            throw new \RuntimeException(sprintf('Pid file %s is not exists', $fileName));
        }
        $pid = (int) file_get_contents($fileName);
        if ($pid > 0)
        {
            \Swoole\Process::kill($pid);
        }
        else
        {
            throw new \RuntimeException(sprintf('Pid does not exists in file %s', $fileName));
        }
    }

    /**
     * 重新加载服务器.
     */
    public static function reloadServer(): void
    {
        $fileName = Config::get('@app.mainServer.configs.pid_file', ImiUtil::getRuntimePath('swoole.pid'));
        if (!is_file($fileName))
        {
            throw new \RuntimeException(sprintf('Pid file %s is not exists', $fileName));
        }
        $pid = json_decode(file_get_contents($fileName), true);
        if ($pid > 0)
        {
            \Swoole\Process::kill((int) $pid, \SIGUSR1);
        }
        else
        {
            throw new \RuntimeException(sprintf('Pid does not exists in file %s', $fileName));
        }
    }
}
