<?php

declare(strict_types=1);

namespace Imi\Server\TcpServer\Parser;

use Imi\Bean\Parser\BaseParser;
use Imi\Server\TcpServer\Route\Annotation\TcpController;
use Imi\Util\Traits\TServerAnnotationParser;

/**
 * 控制器注解处理器.
 */
class TcpControllerParser extends BaseParser
{
    use TServerAnnotationParser;

    public function __construct()
    {
        $this->controllerAnnotationClass = TcpController::class;
    }

    /**
     * 处理方法.
     *
     * @param \Imi\Bean\Annotation\Base $annotation 注解类
     * @param string                    $className  类名
     * @param string                    $target     注解目标类型（类/属性/方法）
     * @param string                    $targetName 注解目标名称
     */
    public function parse(\Imi\Bean\Annotation\Base $annotation, string $className, string $target, string $targetName): void
    {
    }
}
