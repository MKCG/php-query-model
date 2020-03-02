<?php

namespace MKCG\Model\DBAL;

class Result implements \JsonSerializable
{
    private $content;
    private $count;
    private $aggregations = [];
    private $includedIdsFormatter;

    public static function make(iterable $content, string $entityClass)
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

    public function setAggregations(iterable $aggregations) : self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getAggregations() : iterable
    {
        return $this->aggregations;
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

    public function jsonSerialize()
    {
        return [
            'content' => $this->content,
            'count' => $this->count,
            'aggregations' => $this->aggregations
        ];
    }
}
