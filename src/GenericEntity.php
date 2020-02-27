<?php

namespace MKCG\Model;

class GenericEntity implements \ArrayAccess, \JsonSerializable
{
    private $content;

    public function __construct(array $content)
    {
        $this->content = $content;
    }

    public function offsetExists($offset)
    {
        return isset($this->content[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->content[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $this->content[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        if (isset($this->content[$offset])) {
            unset($this->content[$offset]);
        }
    }

    public function jsonSerialize()
    {
        return $this->content;
    }
}
