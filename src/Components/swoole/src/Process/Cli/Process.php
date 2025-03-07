<?php

declare(strict_types=1);

namespace Imi\Swoole\Process\Cli;

use Imi\Bean\Scanner;
use Imi\Cli\Annotation\Argument;
use Imi\Cli\Annotation\Command;
use Imi\Cli\Annotation\CommandAction;
use Imi\Cli\Annotation\Option;
use Imi\Cli\ArgType;
use Imi\Cli\Contract\BaseCommand;
use Imi\Event\Event;
use Imi\Swoole\Process\ProcessManager;
use Imi\Swoole\Process\ProcessPoolManager;
use RuntimeException;

/**
 * @Command("process")
 */
class Process extends BaseCommand
{
    /**
     * 开启一个进程，可以任意添加参数.
     *
     * @CommandAction(name="start", dynamicOptions=true, description="开启一个进程")
     *
     * @Argument(name="name", type=ArgType::STRING, required=true, comments="进程名称，通过@Process注解定义")
     * @Option(name="redirectStdinStdout", type=ArgType::BOOL, default=null, comments="重定向子进程的标准输入和输出。启用此选项后，在子进程内输出内容将不是打印屏幕，而是写入到主进程管道。读取键盘输入将变为从管道中读取数据。默认为阻塞读取。")
     * @Option(name="pipeType", type=ArgType::INT, default=null, comments="管道类型，启用$redirectStdinStdout后，此选项将忽略用户参数，强制为1。如果子进程内没有进程间通信，可以设置为 0")
     */
    public function start(string $name, ?bool $redirectStdinStdout, ?int $pipeType): void
    {
        Event::one('IMI.SWOOLE.MAIN_COROUTINE.AFTER', function () use ($name, $redirectStdinStdout, $pipeType) {
            // 加载服务器注解
            Scanner::scanVendor();
            Scanner::scanApp();
            $process = ProcessManager::create($name, $_SERVER['argv'], $redirectStdinStdout, $pipeType);
            $process->start();
            $result = \Swoole\Process::wait(true);
            $this->output->writeln('Process exit! pid:' . $result['pid'] . ', code:' . $result['code'] . ', signal:' . $result['signal']);
            exit($result['code']);
        });
    }

    /**
     * 开启一个进程池，可以任意添加参数.
     *
     * @CommandAction(name="pool", dynamicOptions=true, description="开启一个进程池")
     *
     * @Argument(name="name", type=ArgType::STRING, required=true, comments="进程池名称，通过@ProcessPool注解定义")
     * @Option(name="worker", type=ArgType::INT, default=null, comments="进程数量，不传则根据注解配置设定")
     * @Option(name="ipcType", type=ArgType::INT, default=null, comments="进程间通信的模式，默认为0表示不使用任何进程间通信特性，不传则根据注解配置设定")
     * @Option(name="msgQueueKey", type=ArgType::STRING, default=null, comments="消息队列键，不传则根据注解配置设定")
     */
    public function pool(string $name, ?int $worker, ?int $ipcType, ?string $msgQueueKey): void
    {
        Event::one('IMI.SWOOLE.MAIN_COROUTINE.AFTER', function () use ($name, $worker, $ipcType, $msgQueueKey) {
            // 加载服务器注解
            Scanner::scanVendor();
            Scanner::scanApp();
            $processPool = ProcessPoolManager::create($name, $worker, $_SERVER['argv'], $ipcType, $msgQueueKey);
            $processPool->start();
        });
    }

    /**
     * 运行一个进程.
     *
     * @CommandAction(name="run", dynamicOptions=true, description="运行一个进程")
     *
     * @Argument(name="name", type=ArgType::STRING, required=true, comments="进程名称，通过@Process注解定义")
     */
    public function run(string $name): void
    {
        Event::one('IMI.SWOOLE.MAIN_COROUTINE.AFTER', function () use ($name) {
            // 加载服务器注解
            Scanner::scanVendor();
            Scanner::scanApp();
            $processOption = ProcessManager::get($name);
            if (null === $processOption)
            {
                throw new RuntimeException(sprintf('Not found process %s', $name));
            }
            $callable = ProcessManager::getProcessCallable($_SERVER['argv'], $name, $processOption);
            $callable(new \Imi\Swoole\Process\Process(function () {
            }));
        });
    }
}
