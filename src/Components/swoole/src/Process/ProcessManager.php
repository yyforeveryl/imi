<?php

declare(strict_types=1);

namespace Imi\Swoole\Process;

use Imi\App;
use Imi\Bean\Scanner;
use Imi\Event\Event;
use Imi\Server\ServerManager;
use Imi\Swoole\Process\Exception\ProcessAlreadyRunException;
use Imi\Swoole\Server\Contract\ISwooleServer;
use Imi\Swoole\Util\Coroutine;
use Imi\Swoole\Util\Imi as SwooleImi;
use Imi\Util\File;
use Imi\Util\Imi;
use Imi\Util\Process\ProcessAppContexts;
use Imi\Util\Process\ProcessType;
use Swoole\ExitException;
use Swoole\Process;

/**
 * 进程管理类.
 */
class ProcessManager
{
    private static array $map = [];

    /**
     * 锁集合.
     */
    private static array $lockMap = [];

    /**
     * 挂载在管理进程下的进程列表.
     *
     * @var \Swoole\Process[]
     */
    private static array $managerProcesses = [];

    private function __construct()
    {
    }

    public static function getMap(): array
    {
        return self::$map;
    }

    public static function setMap(array $map): void
    {
        self::$map = $map;
    }

    /**
     * 增加映射关系.
     */
    public static function add(string $name, string $className, array $options): void
    {
        self::$map[$name] = [
            'className' => $className,
            'options'   => $options,
        ];
    }

    /**
     * 获取配置.
     */
    public static function get(string $name): ?array
    {
        return self::$map[$name] ?? null;
    }

    /**
     * 创建进程
     * 本方法无法在控制器中使用
     * 返回\Swoole\Process对象实例.
     */
    public static function create(string $name, array $args = [], ?bool $redirectStdinStdout = null, ?int $pipeType = null, ?string $alias = null): Process
    {
        $processOption = self::get($name);
        if (null === $processOption)
        {
            throw new \RuntimeException(sprintf('Process %s not found', $name));
        }
        if ($processOption['options']['unique'] && static::isRunning($name))
        {
            throw new ProcessAlreadyRunException(sprintf('Process %s already run', $name));
        }
        if (null === $redirectStdinStdout)
        {
            $redirectStdinStdout = $processOption['options']['redirectStdinStdout'];
        }
        if (null === $pipeType)
        {
            $pipeType = $processOption['options']['pipeType'];
        }
        $process = new \Swoole\Process(static::getProcessCallable($args, $name, $processOption, $alias), $redirectStdinStdout, $pipeType);

        return $process;
    }

    /**
     * 获取进程回调.
     */
    public static function getProcessCallable(array $args, string $name, array $processOption, ?string $alias = null): callable
    {
        return function (Process $swooleProcess) use ($args, $name, $processOption, $alias) {
            App::set(ProcessAppContexts::PROCESS_TYPE, ProcessType::PROCESS, true);
            App::set(ProcessAppContexts::PROCESS_NAME, $name, true);
            // 设置进程名称
            $processName = $name;
            if ($alias)
            {
                $processName .= '#' . $processName;
            }
            SwooleImi::setProcessName('process', [
                'processName'   => $processName,
            ]);
            // 随机数播种
            mt_srand();
            $exitCode = 0;
            $callable = function () use ($swooleProcess, $args, $name, $processOption, &$exitCode) {
                if ($inCoroutine = Coroutine::isIn())
                {
                    Coroutine::defer(function () use ($name, $swooleProcess) {
                        // 进程结束事件
                        Event::trigger('IMI.PROCESS.END', [
                            'name'      => $name,
                            'process'   => $swooleProcess,
                        ]);
                    });
                }
                try
                {
                    if ($processOption['options']['unique'] && !static::lockProcess($name))
                    {
                        throw new \RuntimeException('Lock process lock file error');
                    }
                    // 加载服务器注解
                    Scanner::scanVendor();
                    Scanner::scanApp();
                    // 进程开始事件
                    Event::trigger('IMI.PROCESS.BEGIN', [
                        'name'      => $name,
                        'process'   => $swooleProcess,
                    ]);
                    // 执行任务
                    $processInstance = App::getBean($processOption['className'], $args);
                    $processInstance->run($swooleProcess);
                    if ($processOption['options']['unique'])
                    {
                        static::unlockProcess($name);
                    }
                }
                catch (ExitException $e)
                {
                    $exitCode = $e->getStatus();
                }
                catch (\Throwable $th)
                {
                    App::getBean('ErrorLog')->onException($th);
                    $exitCode = 255;
                }
                finally
                {
                    if (!$inCoroutine)
                    {
                        // 进程结束事件
                        Event::trigger('IMI.PROCESS.END', [
                            'name'      => $name,
                            'process'   => $swooleProcess,
                        ]);
                    }
                }
            };
            if ($processOption['options']['co'])
            {
                // 强制开启进程协程化
                \Swoole\Coroutine\run($callable);
            }
            else
            {
                $callable();
            }
            if (0 != $exitCode)
            {
                exit($exitCode);
            }
        };
    }

    /**
     * 进程是否已在运行，只有unique为true时有效.
     */
    public static function isRunning(string $name): bool
    {
        $processOption = self::get($name);
        if (null === $processOption)
        {
            return false;
        }
        if (!$processOption['options']['unique'])
        {
            return false;
        }
        $fileName = static::getLockFileName($name);
        if (!is_file($fileName))
        {
            return false;
        }
        $fp = fopen($fileName, 'w+');
        if (false === $fp)
        {
            return false;
        }
        if (!flock($fp, \LOCK_EX | \LOCK_NB))
        {
            fclose($fp);

            return true;
        }
        flock($fp, \LOCK_UN);
        fclose($fp);
        unlink($fileName);

        return false;
    }

    /**
     * 运行进程，协程挂起等待进程执行返回
     * 不返回\Swoole\Process对象实例
     * 执行失败返回false，执行成功返回数组，包含了进程退出的状态码、信号、输出内容。
     * array(
     *     'code'   => 0,
     *     'signal' => 0,
     *     'output' => '',
     * );.
     */
    public static function run(string $name, array $args = [], ?bool $redirectStdinStdout = null, ?int $pipeType = null): array
    {
        $cmd = Imi::getImiCmd('process/run', [$name], $args);
        if (null !== $redirectStdinStdout)
        {
            $cmd .= ' --redirectStdinStdout ' . $redirectStdinStdout;
        }
        if (null !== $pipeType)
        {
            $cmd .= ' --pipeType ' . $pipeType;
        }

        return Coroutine::exec($cmd);
    }

    /**
     * 运行进程，创建一个协程执行进程，无法获取进程执行结果
     * 执行失败返回false，执行成功返回数组，包含了进程退出的状态码、信号、输出内容。
     * array(
     *     'code'   => 0,
     *     'signal' => 0,
     *     'output' => '',
     * );.
     */
    public static function coRun(string $name, array $args = [], ?bool $redirectStdinStdout = null, ?int $pipeType = null): void
    {
        go(function () use ($name, $args, $redirectStdinStdout, $pipeType) {
            static::run($name, $args, $redirectStdinStdout, $pipeType);
        });
    }

    /**
     * 挂靠Manager进程运行进程.
     */
    public static function runWithManager(string $name, array $args = [], ?bool $redirectStdinStdout = null, ?int $pipeType = null, ?string $alias = null): ?Process
    {
        $process = static::create($name, $args, $redirectStdinStdout, $pipeType, $alias);
        /** @var ISwooleServer $server */
        $server = ServerManager::getServer('main', ISwooleServer::class);
        $swooleServer = $server->getSwooleServer();
        $swooleServer->addProcess($process);
        static::$managerProcesses[$name][$alias] = $process;

        return $process;
    }

    /**
     * 获取挂载在管理进程下的进程.
     */
    public static function getProcessWithManager(string $name, ?string $alias = null): ?Process
    {
        return static::$managerProcesses[$name][$alias] ?? null;
    }

    /**
     * 锁定进程，实现unique.
     */
    private static function lockProcess(string $name): bool
    {
        $fileName = static::getLockFileName($name);
        $fp = fopen($fileName, 'w+');
        if (false === $fp)
        {
            return false;
        }
        if (!flock($fp, \LOCK_EX | \LOCK_NB))
        {
            fclose($fp);

            return false;
        }
        static::$lockMap[$name] = [
            'fileName'  => $fileName,
            'fp'        => $fp,
        ];

        return true;
    }

    /**
     * 解锁进程，实现unique.
     */
    private static function unlockProcess(string $name): bool
    {
        $lockMap = &static::$lockMap;
        if (!isset($lockMap[$name]))
        {
            return false;
        }
        $lockItem = $lockMap[$name];
        $fp = $lockItem['fp'];
        if (flock($fp, \LOCK_UN) && fclose($fp))
        {
            unlink($lockItem['fileName']);
            unset($lockMap[$name]);

            return true;
        }

        return false;
    }

    /**
     * 获取文件锁的文件名.
     */
    private static function getLockFileName(string $name): string
    {
        $path = Imi::getRuntimePath(str_replace('\\', '-', App::getNamespace()), 'processLock');
        if (!is_dir($path))
        {
            File::createDir($path);
        }

        return File::path($path, $name . '.lock');
    }
}
