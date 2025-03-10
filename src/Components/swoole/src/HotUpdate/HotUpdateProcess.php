<?php

declare(strict_types=1);

namespace Imi\Swoole\HotUpdate;

use Imi\Aop\Annotation\Inject;
use Imi\App;
use Imi\Bean\Annotation\Bean;
use Imi\Event\Event;
use Imi\Log\ErrorLog;
use Imi\Log\Log;
use Imi\Pool\Annotation\PoolClean;
use Imi\Swoole\Process\Annotation\Process;
use Imi\Swoole\Process\BaseProcess;
use Imi\Swoole\Util\Imi as SwooleImiUtil;
use Imi\Util\Imi;
use Swoole\Timer;

/**
 * @Bean("hotUpdate")
 * @Process(name="hotUpdate", unique=true)
 */
class HotUpdateProcess extends BaseProcess
{
    /**
     * 监视器类.
     */
    protected string $monitorClass = \Imi\HotUpdate\Monitor\FileMTime::class;

    /**
     * 每次检测时间间隔，单位：秒（有可能真实时间会大于设定的时间）.
     */
    protected int $timespan = 1;

    /**
     * 包含的路径.
     */
    protected array $includePaths = [];

    /**
     * 排除的路径.
     */
    protected array $excludePaths = [];

    /**
     * 默认监视路径.
     */
    protected ?array $defaultPath = null;

    /**
     * 是否开启热更新，默认开启.
     */
    protected bool $status = true;

    /**
     * 热更新检测，更改的文件列表，储存在的文件名.
     */
    protected string $changedFilesFile;

    /**
     * buildRuntime resource.
     *
     * @var resource|null
     */
    private $buildRuntimeHandler = null;

    /**
     * buildRuntime pipes.
     */
    private ?array $buildRuntimePipes = null;

    /**
     * @Inject("ErrorLog")
     */
    protected ErrorLog $errorLog;

    /**
     * 开始时间.
     */
    private float $beginTime;

    /**
     * 是否正在构建中.
     */
    private bool $building = false;

    /**
     * 构建运行时计时器ID.
     */
    private int $buildRuntimeTimerId = 0;

    /**
     * @PoolClean
     */
    public function run(\Swoole\Process $process): void
    {
        if (!$this->status)
        {
            return;
        }
        $this->changedFilesFile = Imi::getRuntimePath('changedFilesFile');
        file_put_contents($this->changedFilesFile, '');
        if (null === $this->defaultPath)
        {
            $this->defaultPath = Imi::getNamespacePaths(App::getNamespace());
        }
        $this->excludePaths[] = Imi::getRuntimePath();
        Log::info('Process [hotUpdate] start');
        $monitor = App::getBean($this->monitorClass, array_merge($this->defaultPath, $this->includePaths), $this->excludePaths);
        $time = 0;
        $this->initBuildRuntime();
        $this->startBuildRuntimeTimer();
        while (true)
        {
            // 检测间隔延时
            if ($this->timespan > 0)
            {
                $time = $this->timespan - (microtime(true) - $time);
                if ($time <= 0)
                {
                    $time = 10000;
                }
                else
                {
                    $time *= 1000000;
                }
            }
            else
            {
                $time = 10000;
            }
            usleep((int) $time);
            $time = microtime(true);
            // 检查文件是否有修改
            if ($monitor->isChanged())
            {
                $changedFiles = $monitor->getChangedFiles();
                Log::info('Found ' . \count($changedFiles) . ' changed Files:' . \PHP_EOL . implode(\PHP_EOL, $changedFiles));
                file_put_contents($this->changedFilesFile, implode("\n", $changedFiles));
                Log::info('Building runtime...');
                if ($this->building)
                {
                    $this->stopBuildRuntimeTimer();
                    $this->initBuildRuntime();
                    $this->startBuildRuntimeTimer();
                }
                $this->beginBuildRuntime($changedFiles);
            }
        }
    }

    /**
     * 开始构建运行时计时器.
     */
    private function startBuildRuntimeTimer(): void
    {
        $this->buildRuntimeTimerId = Timer::tick(1000, [$this, 'buildRuntimeTimer']);
    }

    /**
     * 停止构建运行时计时器.
     */
    private function stopBuildRuntimeTimer(): void
    {
        Timer::clear($this->buildRuntimeTimerId);
    }

    /**
     * 清除各种缓存.
     */
    private function clearCache(array $changedFiles): void
    {
        static $functions = [
            'apc_clear_cache',
            'opcache_reset',
        ];
        foreach ($functions as $function)
        {
            if (\function_exists($function))
            {
                $function();
            }
        }
        if (\function_exists('opcache_invalidate'))
        {
            foreach ($changedFiles as $file)
            {
                opcache_invalidate($file, true);
            }
        }
    }

    /**
     * 初始化 runtime.
     */
    private function initBuildRuntime(): void
    {
        $this->closeBuildRuntime();
        $cmd = Imi::getImiCmd('imi/buildRuntime', [], [
            'changedFilesFile'  => $this->changedFilesFile,
            'confirm'           => true,
            'app-runtime'       => Imi::getRuntimePath('runtime'),
        ]);
        static $descriptorspec = [
            ['pipe', 'r'],  // 标准输入，子进程从此管道中读取数据
            ['pipe', 'w'],  // 标准输出，子进程向此管道中写入数据
        ];
        $this->buildRuntimeHandler = proc_open(\Imi\cmd($cmd), $descriptorspec, $this->buildRuntimePipes);
        if (false === $this->buildRuntimeHandler)
        {
            throw new \RuntimeException(sprintf('Open "%s" failed', $cmd));
        }
    }

    /**
     * 开始构建 runtime.
     *
     * @param string[] $changedFiles
     */
    private function beginBuildRuntime(array $changedFiles): void
    {
        $this->beginTime = microtime(true);
        $result = null;
        Event::trigger('IMI.HOTUPDATE.BEGIN_BUILD', [
            'changedFiles'      => $changedFiles,
            'changedFilesFile'  => $this->changedFilesFile,
            'result'            => &$result,
        ]);
        // @phpstan-ignore-next-line
        if ($result)
        {
            return;
        }
        $this->building = true;
        try
        {
            $status = proc_get_status($this->buildRuntimeHandler);
            if (!($status['running'] ?? false))
            {
                $this->initBuildRuntime();
            }
            $writeContent = "y\n";
            if (\strlen($writeContent) !== fwrite($this->buildRuntimePipes[0], $writeContent))
            {
                throw new \RuntimeException('Send to buildRuntime process failed');
            }

            while ($tmp = fgets($this->buildRuntimePipes[1]))
            {
                echo $tmp;
            }

            do
            {
                $status = proc_get_status($this->buildRuntimeHandler);
                if (!($status['running'] ?? false))
                {
                    break;
                }
                usleep(1000);
            } while (true);

            if (0 !== $status['exitcode'])
            {
                Log::error('Build runtime failed!');

                return;
            }
            // 清除各种缓存
            $this->clearCache($changedFiles);
            Log::info('Build time use: ' . (microtime(true) - $this->beginTime) . ' sec');
            // 执行重新加载
            Log::info('Reloading server...');
            SwooleImiUtil::reloadServer();
        }
        catch (\Throwable $th)
        {
            throw $th;
        }
        finally
        {
            $this->building = false;
        }
    }

    /**
     * 关闭 runtime 进程.
     */
    private function closeBuildRuntime(): void
    {
        $closePipes = function (?array $buildRuntimePipes) {
            if (null !== $buildRuntimePipes)
            {
                foreach ($buildRuntimePipes as $pipe)
                {
                    fclose($pipe);
                }
            }
        };
        if ($this->buildRuntimeHandler)
        {
            $buildRuntimeHandler = $this->buildRuntimeHandler;
            $buildRuntimePipes = $this->buildRuntimePipes;
            $status = proc_get_status($buildRuntimeHandler);
            if ($status['running'] ?? false)
            {
                $writeContent = "n\n";
                fwrite($buildRuntimePipes[0], $writeContent);
            }
            $this->buildRuntimeHandler = null;
            $this->buildRuntimePipes = null;
            $closePipes($buildRuntimePipes);
            proc_close($buildRuntimeHandler);
        }
        else
        {
            $closePipes($this->buildRuntimePipes);
            $this->buildRuntimePipes = null;
        }
    }

    /**
     * 定时器，用于监听构建进程.
     */
    public function buildRuntimeTimer(): void
    {
        if (!$this->buildRuntimeHandler)
        {
            $this->initBuildRuntime();

            return;
        }
        $status = proc_get_status($this->buildRuntimeHandler);
        if (!($status['running'] ?? false))
        {
            $this->initBuildRuntime();
        }
    }
}
