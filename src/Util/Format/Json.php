<?php

declare(strict_types=1);

namespace Imi\Util\Format;

class Json implements IFormat
{
    /**
     * 编码为存储格式.
     *
     * @param mixed $data
     */
    public function encode($data): string
    {
        return json_encode($data);
    }

    /**
     * 解码为php变量.
     *
     * @return mixed
     */
    public function decode(string $data)
    {
        return json_decode($data, true);
    }
}
