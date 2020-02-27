<?php

namespace MKCG\Model\DBAL;

class Result
{
    private $content;
    private $count;
    private $includedIdsFormatter;

    public static function make(array $content, string $entityClass)
    {
        $result = new static();
        $result->content = array_map(function($item) use ($entityClass) {
            return new $entityClass($item);
        }, $content);

        return $result;
    }

    public function setCount(int $count) : self
    {
        $this->count = $count;
        return $this;
    }

    public function getCount() : ?int
    {
        return $this->count;
    }

    public function getContent() : iterable
    {
        return $this->content;
    }

    public function setIncludedIdsFormatter(callable $formatter) : self
    {
        $this->includedIdsFormatter = $formatter;
        return $this;
    }

    public function getIncludedIds(?callable $formatter = null) : array
    {
        if ($formatter !== null) {
            return call_user_func($formatter, $this->content);
        }

        if ($this->includedIdsFormatter !== null) {
            return call_user_func($this->includedIdsFormatter, $this->content);
        }

        return [];
    }
}
