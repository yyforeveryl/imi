<?php

declare(strict_types=1);

namespace Imi\Test\Component\Tests;

use Imi\Util\File;
use Imi\Util\Imi;
use PHPUnit\Framework\Assert;

/**
 * @testdox Cache File Handler
 */
class CacheFile1Test extends BaseCacheTest
{
    protected string $cacheName = 'file1';

    public function testSetAndGet(): void
    {
        parent::testSetAndGet();
        $path = File::path(Imi::getNamespacePath('Imi\Test\Component\.runtime\cache'), md5('imi'));
        Assert::assertTrue(is_file($path));
    }
}
