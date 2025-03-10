<?php

declare(strict_types=1);

namespace Imi\Cli\Annotation;

use Imi\Bean\Annotation\Base;
use Imi\Bean\Annotation\Parser;

/**
 * 可选项参数注解.
 *
 * @Annotation
 * @Target("METHOD")
 * @Parser("Imi\Cli\Parser\ToolParser")
 *
 * @property string      $name     参数名称
 * @property string|null $shortcut 短名称
 * @property string|null $type     参数类型
 * @property mixed       $default  默认值
 * @property bool        $required 是否是必选参数
 * @property string      $comments 注释
 * @property string      $to       将参数值绑定到指定名称的参数
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class Option extends Base
{
    /**
     * 只传一个参数时的参数名.
     */
    protected ?string $defaultFieldName = 'name';

    /**
     * @param mixed $default
     */
    public function __construct(?array $__data = null, string $name = '', ?string $shortcut = null, ?string $type = null, $default = null, bool $required = false, string $comments = '', string $to = '')
    {
        parent::__construct(...\func_get_args());
    }
}
