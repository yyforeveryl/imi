<?php

declare(strict_types=1);

namespace Imi\Cli;

use Imi\App;
use Imi\Bean\Scanner;
use Imi\Core\App\Contract\BaseApp;
use Imi\Core\App\Enum\LoadRuntimeResult;
use Imi\Log\LogAppContexts;
use Imi\Log\LogLevel;
use Imi\Util\Imi;
use Imi\Util\Process\ProcessAppContexts;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CliApp extends BaseApp
{
    /**
     * @var Application
     */
    protected Application $cli;

    /**
     * @var EventDispatcher
     */
    protected EventDispatcher $cliEventDispatcher;

    /**
     * @var ArgvInput
     */
    protected ArgvInput $input;

    /**
     * 构造方法.
     *
     * @param string $namespace
     */
    public function __construct(string $namespace)
    {
        parent::__construct($namespace);
        App::set(ProcessAppContexts::SCRIPT_NAME, realpath($_SERVER['SCRIPT_FILENAME']));
        App::set(LogAppContexts::CORE_HANDLERS, [
            [
                'class'     => \Imi\Log\Handler\Console::class,
                'options'   => [
                    'levels'    => [
                        LogLevel::INFO,
                    ],
                    'format'    => '{Y}-{m}-{d} {H}:{i}:{s} <info>[{level}]</info> {message}',
                ],
            ],
            [
                'class'     => \Imi\Log\Handler\Console::class,
                'options'   => [
                    'levels' => [
                        LogLevel::DEBUG,
                        LogLevel::NOTICE,
                        LogLevel::WARNING,
                    ],
                    'format' => '{Y}-{m}-{d} {H}:{i}:{s} <comment>[{level}]</comment> {message} {errorFile}:{errorLine}',
                ],
            ],
            [
                'class'     => \Imi\Log\Handler\Console::class,
                'options'   => [
                    'levels' => [
                        LogLevel::ALERT,
                        LogLevel::CRITICAL,
                        LogLevel::EMERGENCY,
                        LogLevel::ERROR,
                    ],
                    'format' => '{Y}-{m}-{d} {H}:{i}:{s} <fg=red>[{level}]</> <comment>{message}</comment> {errorFile}:{errorLine}' . \PHP_EOL . 'Stack trace:' . \PHP_EOL . '{trace}',
                    'length' => 1024,
                ],
            ],
        ], true);
        $this->input = new ArgvInput();
        $this->cliEventDispatcher = $dispatcher = new EventDispatcher();
        $this->cli = $cli = new Application('imi', App::getImiVersion());
        $cli->setDispatcher($dispatcher);
        $cli->setCatchExceptions(false);

        $definition = $cli->getDefinition();
        $definition->addOption(
            new InputOption(
                'app-namespace',
                null,
                InputOption::VALUE_OPTIONAL,
                'Your project app namespace'
            )
        );
        $definition->addOption(
            new InputOption(
                'imi-runtime',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set imi runtime file',
                null,
            )
        );
        $definition->addOption(
            new InputOption(
                'app-runtime',
                null,
                InputOption::VALUE_OPTIONAL,
                'Set app runtime file',
                null,
            )
        );
    }

    /**
     * 加载运行时.
     *
     * @return int
     */
    public function loadRuntime(): int
    {
        $this->initRuntime();
        $input = new ArgvInput();
        // 尝试加载项目运行时
        $appRuntimeFile = $input->getParameterOption('--app-runtime');
        if (false !== $appRuntimeFile && Imi::loadRuntimeInfo($appRuntimeFile))
        {
            return LoadRuntimeResult::ALL;
        }
        // 尝试加载 imi 框架运行时
        if ($file = $input->getParameterOption('--imi-runtime'))
        {
            // 尝试加载指定 runtime
            $result = Imi::loadRuntimeInfo($file);
        }
        else
        {
            // 尝试加载默认 runtime
            $result = Imi::loadRuntimeInfo(Imi::getRuntimePath('imi-runtime'));
        }
        if (!$result)
        {
            // 不使用缓存时去扫描
            Scanner::scanImi();

            return LoadRuntimeResult::IMI_LOADED;
        }

        // @phpstan-ignore-next-line
        return $result ? LoadRuntimeResult::ALL : 0;
    }

    /**
     * 初始化.
     *
     * @return void
     */
    public function init(): void
    {
        $this->addCommands();
    }

    private function addCommands(): void
    {
        foreach (CliManager::getCommands() as $command)
        {
            $command = new ImiCommand($command['commandName'], $command['actionName'], $command['className'], $command['methodName'], $command['dynamicOptions']);
            if (!$this->cli->has($command->getName()))
            {
                $this->cli->add($command);
            }
        }
    }

    /**
     * 获取应用类型.
     *
     * @return string
     */
    public function getType(): string
    {
        return 'cli';
    }

    /**
     * 运行应用.
     *
     * @return void
     */
    public function run(): void
    {
        try
        {
            $this->cli->run(new ImiArgvInput());
        }
        catch (\Exception $th)
        {
            /** @var \Imi\Log\ErrorLog $errorLog */
            $errorLog = App::getBean('ErrorLog');
            $errorLog->onException($th);
            exit(255);
        }
    }

    /**
     * Get the value of cli.
     *
     * @return Application
     */
    public function getCli(): Application
    {
        return $this->cli;
    }
}
