<?php

declare(strict_types=1);

namespace Imi\Server\Http\SuperGlobals;

use Imi\RequestContext;

class Get implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param mixed $offset
     * @param mixed $value
     *
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        trigger_error('imi does not support to assign values to $_GET', \E_USER_WARNING);
    }

    /**
     * @param mixed $offset
     *
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        /** @var \Imi\Server\Http\Message\Request $request */
        $request = RequestContext::get('request');

        return null !== $request->get($offset);
    }

    /**
     * @param mixed $offset
     *
     * @return void
     */
    public function offsetUnset($offset): void
    {
        trigger_error('imi does not support to unset values from $_GET', \E_USER_WARNING);
    }

    /**
     * @param mixed $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        /** @var \Imi\Server\Http\Message\Request $request */
        $request = RequestContext::get('request');

        return $request->get($offset);
    }

    public function __debugInfo(): array
    {
        return $this->jsonSerialize();
    }

    public function jsonSerialize()
    {
        /** @var \Imi\Server\Http\Message\Request $request */
        $request = RequestContext::get('request');

        return $request->get();
    }
}
