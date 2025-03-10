<?php

declare(strict_types=1);

namespace Imi\Util\Format;

class PhpSession implements IFormat
{
    /**
     * 编码为存储格式.
     *
     * @param mixed $data
     */
    public function encode($data): string
    {
        $result = '';
        foreach ($data as $k => $v)
        {
            $result .= $k . '|' . serialize($v);
        }

        return $result;
    }

    /**
     * 解码为php变量.
     *
     * @return mixed
     */
    public function decode(string $data)
    {
        $result = [];
        $offset = 0;
        $length = \strlen($data);
        while ($offset < $length)
        {
            if (!strstr(substr($data, $offset), '|'))
            {
                return [];
            }
            $pos = strpos($data, '|', $offset);
            $num = $pos - $offset;
            $varname = substr($data, $offset, $num);
            $offset += $num + 1;
            $dataItem = unserialize(substr($data, $offset));
            $result[$varname] = $dataItem;
            $offset += \strlen(serialize($dataItem));
        }

        return $result;
    }
}
