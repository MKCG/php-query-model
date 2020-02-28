<?php

namespace MKCG\Model\DBAL;

class QueryCriteria
{
    private static $supportedFilters = [
        FilterInterface::FILTER_IN,
        FilterInterface::FILTER_NOT_IN,
        FilterInterface::FILTER_LESS_THAN,
        FilterInterface::FILTER_LESS_THAN_EQUAL,
        FilterInterface::FILTER_GREATER_THAN,
        FilterInterface::FILTER_GREATER_THAN_EQUAL,
        FilterInterface::FILTER_FULLTEXT_MATCH
    ];

    private static $supportedAggregations = [
        AggregationInterface::AGG_TERMS_CARDINALITY,
        AggregationInterface::AGG_FACET,
    ];

    private static $arrayFilters = [
        FilterInterface::FILTER_IN,
        FilterInterface::FILTER_NOT_IN
    ];

    private $criteria = [];
    private $currentCollection = '';

    public function toArray() : array
    {
        return $this->criteria;
    }

    public function forCollection(string $collection) : self
    {
        $this->currentCollection = $collection;
        return $this;
    }

    public function addFilter(string $field, string $filterType, $values) : self
    {
        $this->assert();

        if (!in_array($filterType, self::$supportedFilters)) {
            throw new \LogicException("Invalid filter type : " . $filterType);
        }

        if (is_array($values) && !in_array($filterType, self::$arrayFilters)) {
            throw new \LogicException(sprintf("The filter type '%s' does not support array as argument", $filterType));
        }

        if (!isset($this->criteria[$this->currentCollection]['filters'])) {
            $this->criteria[$this->currentCollection]['filters'] = [];
        }

        if (!isset($this->criteria[$this->currentCollection]['filters'][$field])) {
            $this->criteria[$this->currentCollection]['filters'][$field] = [];
        }

        $this->criteria[$this->currentCollection]['filters'][$field][$filterType] = $values;

        return $this;
    }

    public function addCustomFilter(string $name) : self
    {
        $this->assert();

        if (!isset($this->criteria[$this->currentCollection]['custom_filters'])) {
            $this->criteria[$this->currentCollection]['custom_filters'] = [];
        }

        $this->criteria[$this->currentCollection]['custom_filters'][] = $name;

        return $this;
    }

    public function addCallableFilter(callable $callable) : self
    {
        $this->assert();

        if (!isset($this->criteria[$this->currentCollection]['callable_filters'])) {
            $this->criteria[$this->currentCollection]['callable_filters'] = [];
        }

        $this->criteria[$this->currentCollection]['callable_filters'][] = $callable;

        return $this;
    }

    public function addAggregation(string $aggType, array $config) : self
    {
        $this->assert();

        if (!in_array($aggType, self::$supportedAggregations)) {
            throw new \LogicException("Invalid aggregation type : " . $aggType);
        }

        switch ($aggType) {
            case AggregationInterface::AGG_FACET:
            case AggregationInterface::AGG_TERMS_CARDINALITY:
                if (empty($config['field']) || !is_string($config['field'])) {
                    throw new \LogicException("Invalid aggregation field");
                }

                break;
        }

        if (!isset($this->criteria[$this->currentCollection]['aggregations'])) {
            $this->criteria[$this->currentCollection]['aggregations'] = [];
        }

        $this->criteria[$this->currentCollection]['aggregations'][] = [
            'type' => $aggType,
            'config' => $config
        ];

        return $this;
    }

    public function addSort(string $field, string $dir) : self
    {
        $this->assert();
        $dir = strtoupper(trim($dir));

        if (!in_array($dir, ['ASC', 'DESC'])) {
            throw new \LogicException("Sort order is Invalid");
        }

        if (!isset($this->criteria[$this->currentCollection]['sort'])) {
            $this->criteria[$this->currentCollection]['sort'] = [];
        }

        $this->criteria[$this->currentCollection]['sort'][] = [ $field , $dir ];
        return $this;
    }

    public function addOption(string $name, $value) : self
    {
        $this->assert();

        if (!isset($this->criteria[$this->currentCollection]['options'])) {
            $this->criteria[$this->currentCollection]['options'] = [];
        }

        $this->criteria[$this->currentCollection]['options'][$name] = $value;

        return $this;
    }

    public function setLimit(int $number) : self
    {
        return $this->setNumber('limit', $number);
    }

    public function setLimitByParent(int $number) : self
    {
        return $this->setNumber('limit_by_parent', $number);
    }

    public function setOffset(int $number) : self
    {
        return $this->setNumber('offset', $number);
    }

    private function setNumber(string $config, int $number) : self
    {
        $this->assert();

        if ($number < 0) {
            throw new \LogicException($config . " must be greater than or equal to 0");
        }

        $this->criteria[$this->currentCollection][$config] = $number;
        return $this;
    }

    private function assert()
    {
        if ($this->currentCollection === '') {
            throw new \LogicException("Collection is undefined");
        }

        if (!isset($this->criteria[$this->currentCollection])) {
            $this->criteria[$this->currentCollection] = [];
        }
    }
}
