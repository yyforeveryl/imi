<?php

declare(strict_types=1);

namespace Imi\Db\Query\Where;

use Imi\Db\Mysql\Consts\LogicalOperator;
use Imi\Db\Query\Interfaces\IBaseWhere;
use Imi\Db\Query\Interfaces\IQuery;
use Imi\Db\Query\Interfaces\IWhereBrackets;
use Imi\Db\Query\Traits\TRaw;

class WhereBrackets extends BaseWhere implements IWhereBrackets
{
    use TRaw;

    /**
     * 回调.
     *
     * @var callable
     */
    protected $callback;

    /**
     * 绑定的数据们.
     */
    protected array $binds = [];

    public function __construct(callable $callback = null, string $logicalOperator = LogicalOperator::AND)
    {
        $this->callback = $callback;
        $this->logicalOperator = $logicalOperator;
    }

    /**
     * 回调.
     */
    public function getCallback(): callable
    {
        return $this->callback;
    }

    /**
     * 逻辑运算符.
     */
    public function getLogicalOperator(): string
    {
        return $this->logicalOperator;
    }

    /**
     * 回调.
     */
    public function setCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * 逻辑运算符.
     */
    public function setLogicalOperator(string $logicalOperator): void
    {
        $this->logicalOperator = $logicalOperator;
    }

    /**
     * 获取无逻辑的字符串.
     */
    public function toStringWithoutLogic(IQuery $query): string
    {
        if ($this->isRaw)
        {
            return $this->rawSQL;
        }
        $binds = &$this->binds;
        $callResult = ($this->callback)();
        if (\is_array($callResult))
        {
            $result = '(';
            foreach ($callResult as $i => $callResultItem)
            {
                if ($callResultItem instanceof IBaseWhere)
                {
                    if (0 === $i)
                    {
                        $result .= $callResultItem->toStringWithoutLogic($query) . ' ';
                    }
                    else
                    {
                        $result .= $callResultItem->getLogicalOperator() . ' ' . $callResultItem->toStringWithoutLogic($query) . ' ';
                    }
                    $binds = array_merge($binds, $callResultItem->getBinds());
                }
                else
                {
                    $result .= $callResultItem . ' ';
                }
            }

            return $result . ')';
        }
        elseif ($callResult instanceof IBaseWhere)
        {
            return $callResult->toStringWithoutLogic($query);
        }
        else
        {
            return (string) $callResult;
        }
    }

    /**
     * 获取绑定的数据们.
     */
    public function getBinds(): array
    {
        return $this->binds;
    }
}
