<?php

declare(strict_types=1);

namespace Imi\Db\Drivers;

use Imi\Db\Interfaces\IStatement;
use Imi\Db\Statement\StatementManager;

abstract class BaseStatement implements IStatement
{
    /**
     * 关闭.
     *
     * @return void
     */
    public function close(): void
    {
        StatementManager::remove($this);
    }
}
