<?php

declare(strict_types=1);

namespace Imi\Bean\Parser;

use Imi\Event\ClassEventManager;

class ClassEventParser extends BaseParser
{
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
        if ($annotation instanceof \Imi\Bean\Annotation\ClassEventListener)
        {
            ClassEventManager::add($annotation->className, $annotation->eventName, $className, $annotation->priority);
        }
    }
}
