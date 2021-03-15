<?php

declare(strict_types=1);

namespace Imi\Db\Query;

use Imi\Db\Query\Interfaces\IBaseWhere;
use Imi\Db\Query\Interfaces\IJoin;
use Imi\Db\Query\Traits\TKeyword;
use Imi\Db\Query\Traits\TRaw;

class Join implements IJoin
{
    use TKeyword;
    use TRaw;

    /**
     * 表名.
     *
     * @var \Imi\Db\Query\Table
     */
    protected Table $table;

    /**
     * 在 join b on a.id=b.id 中的 a.id.
     *
     * @var string
     */
    protected string $left = '';

    /**
     * 在 join b on a.id=b.id 中的 =.
     *
     * @var string
     */
    protected string $operation = '';

    /**
     * join b on a.id=b.id 中的 b.id.
     *
     * @var string
     */
    protected string $right = '';

    /**
     * where条件.
     *
     * @var \Imi\Db\Query\Interfaces\IBaseWhere|null
     */
    protected ?IBaseWhere $where = null;

    /**
     * join类型，默认inner.
     *
     * @var string
     */
    protected string $type = 'inner';

    public function __construct(?string $table = null, ?string $left = null, ?string $operation = null, ?string $right = null, ?string $tableAlias = null, ?IBaseWhere $where = null, string $type = 'inner')
    {
        $this->table = $thisTable = new Table();
        $thisTable->setValue($table);
        if (null !== $tableAlias)
        {
            $thisTable->setAlias($tableAlias);
        }
        $this->left = $left;
        $this->operation = $operation;
        $this->right = $right;
        $this->where = $where;
        $this->type = $type;
    }

    /**
     * 表名.
     *
     * @return string|null
     */
    public function getTable(): ?string
    {
        return (string) $this->table;
    }

    /**
     * 在 join b on a.id=b.id 中的 a.id.
     *
     * @return string|null
     */
    public function getLeft(): ?string
    {
        return $this->left;
    }

    /**
     * 在 join b on a.id=b.id 中的 =.
     *
     * @return string|null
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }

    /**
     * join b on a.id=b.id 中的 b.id.
     *
     * @return string|null
     */
    public function getRight(): ?string
    {
        return $this->right;
    }

    /**
     * 表别名.
     *
     * @return string|null
     */
    public function getTableAlias(): ?string
    {
        return $this->table->getAlias();
    }

    /**
     * where条件.
     *
     * @return \Imi\Db\Query\Interfaces\IBaseWhere|null
     */
    public function getWhere(): ?IBaseWhere
    {
        return $this->where;
    }

    /**
     * join类型，默认inner.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * 设置表名.
     *
     * @param string|null $table
     *
     * @return void
     */
    public function setTable(?string $table = null): void
    {
        $this->table = $thisTable = new Table();
        $thisTable->setValue($table);
    }

    /**
     * 设置在 join b on a.id=b.id 中的 a.id.
     *
     * @param string|null $left
     *
     * @return void
     */
    public function setLeft(?string $left): void
    {
        $this->left = $left;
    }

    /**
     * 设置在 join b on a.id=b.id 中的 =.
     *
     * @param string|null $operation
     *
     * @return void
     */
    public function setOperation(?string $operation): void
    {
        $this->operation = $operation;
    }

    /**
     * 设置join b on a.id=b.id 中的 b.id.
     *
     * @param string|null $right
     *
     * @return void
     */
    public function setRight(?string $right): void
    {
        $this->right = $right;
    }

    /**
     * 设置表别名.
     *
     * @param string|null $tableAlias
     *
     * @return void
     */
    public function setTableAlias(?string $tableAlias): void
    {
        $this->table->setAlias($tableAlias);
    }

    /**
     * 设置where条件.
     *
     * @param IBaseWhere|null $where
     *
     * @return void
     */
    public function setWhere(?IBaseWhere $where): void
    {
        $this->where = $where;
    }

    /**
     * 设置join类型.
     *
     * @param string $type
     *
     * @return void
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function __toString()
    {
        if ($this->isRaw)
        {
            return $this->rawSQL;
        }
        $result = $this->type . ' join ' . $this->table . ' on ' . $this->parseKeyword($this->left) . $this->operation . $this->parseKeyword($this->right);
        if ($this->where instanceof IBaseWhere)
        {
            $result .= ' ' . $this->where;
        }

        return $result;
    }

    /**
     * 获取绑定的数据们.
     *
     * @return array
     */
    public function getBinds(): array
    {
        if ($this->where instanceof IBaseWhere)
        {
            return $this->where->getBinds();
        }
        else
        {
            return [];
        }
    }
}
