<?php

declare(strict_types=1);

namespace Imi\Log;

use Imi\App;
use Imi\Bean\Annotation\Bean;
use Imi\Event\Event;
use Imi\Util\Traits\TBeanRealClass;
use Psr\Log\AbstractLogger;

/**
 * @Bean("Logger")
 */
class Logger extends AbstractLogger
{
    use TBeanRealClass;

    /**
     * 核心处理器.
     *
     * @var array
     */
    protected array $coreHandlers = [];

    /**
     * 扩展处理器.
     *
     * @var array
     */
    protected array $exHandlers = [];

    /**
     * 处理器对象数组.
     *
     * @var \Imi\Log\Handler\Base[]
     */
    protected array $handlers = [];

    /**
     * 日志记录.
     *
     * @var \Imi\Log\Record[]
     */
    protected array $records = [];

    public function __construct()
    {
        $this->coreHandlers = App::get(LogAppContexts::CORE_HANDLERS, []);
    }

    public function __init(): void
    {
        $handlers = &$this->handlers;
        foreach (array_merge($this->coreHandlers, $this->exHandlers) as $handlerOption)
        {
            $handlers[] = App::getBean($handlerOption['class'], $handlerOption['options']);
        }
        Event::on(['IMI.MAIN_SERVER.WORKER.EXIT', 'IMI.PROCESS.END'], function () {
            $this->save();
        }, \Imi\Util\ImiPriority::IMI_MIN + 1);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     *
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        $context = $this->parseContext($context);
        $trace = $context['trace'];
        $logTime = time();
        $record = new Record($level, $message, $context, $trace, $logTime);
        foreach ($this->handlers as $handler)
        {
            $handler->log($record);
        }
    }

    /**
     * 强制保存所有日志.
     *
     * @return void
     */
    public function save(): void
    {
        foreach ($this->handlers as $handler)
        {
            $handler->save();
        }
    }

    /**
     * 获取代码调用跟踪.
     *
     * @param array $backtrace
     *
     * @return array
     */
    protected function getTrace(array $backtrace): array
    {
        $index = null;
        $realClassName = static::__getRealClassName();
        foreach ($backtrace as $i => $item)
        {
            $key = $i + 1;
            if (isset($item['file']) && $realClassName === $item['class'] && isset($backtrace[$key]['file']) && 'AbstractLogger.php' !== basename($backtrace[$key]['file']))
            {
                $index = $i + 2;
                break;
            }
        }
        if (null === $index)
        {
            return [];
        }

        return array_splice($backtrace, $index);
    }

    /**
     * 获取错误文件位置.
     *
     * @param array $backtrace
     *
     * @return array
     */
    public function getErrorFile(array $backtrace): array
    {
        $index = null;
        $realClassName = static::__getRealClassName();
        foreach ($backtrace as $i => $item)
        {
            $key = $i + 1;
            if (isset($item['file']) && $realClassName === $item['class'] && isset($backtrace[$key]['file']) && 'AbstractLogger.php' !== basename($backtrace[$key]['file']))
            {
                $index = $key;
                break;
            }
        }
        $backTraceItem = $backtrace[$index] ?? null;

        return [$backTraceItem['file'] ?? '', $backTraceItem['line'] ?? 0];
    }

    /**
     * 处理context.
     *
     * @param array $context
     *
     * @return array
     */
    private function parseContext(array $context): array
    {
        $debugBackTrace = debug_backtrace();
        $context['trace'] ??= $this->getTrace($debugBackTrace);
        if (!isset($context['errorFile']))
        {
            list($file, $line) = $this->getErrorFile($debugBackTrace);
            $context['errorFile'] = $file;
            $context['errorLine'] = $line;
        }

        return $context;
    }

    /**
     * 增加扩展处理器.
     *
     * @param array $exHandler
     *
     * @return void
     */
    public function addExHandler(array $exHandler): void
    {
        if (\in_array($exHandler, $this->exHandlers))
        {
            return; // 防止重复添加
        }
        $this->exHandlers[] = $exHandler;
        $this->handlers[] = App::getBean($exHandler['class'], $exHandler['options']);
    }
}
