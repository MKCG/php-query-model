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

    public function toArray()
    {
        return $this->valueToArray($this->content);
    }

    private function valueToArray(array $fields)
    {
        $content = [];

        foreach ($fields as $key => $value) {
            if (is_object($value) && is_a($value, GenericEntity::class)) {
                $content[$key] = $value->toArray();
            } else if (!is_array($value)) {
                $content[$key] = $value;
            } else {
                $content[$key] = $this->valueToArray($value);
            }
        }

        return $content;
    }
}
