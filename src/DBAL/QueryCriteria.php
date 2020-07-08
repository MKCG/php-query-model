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
        AggregationInterface::TERMS,
        AggregationInterface::FACET,
        AggregationInterface::AVERAGE,
        AggregationInterface::MIN,
        AggregationInterface::MAX,
        AggregationInterface::QUANTILE,
    ];

    private static $fieldAwareAggregations = [
        AggregationInterface::TERMS,
        AggregationInterface::FACET,
        AggregationInterface::AVERAGE,
        AggregationInterface::MIN,
        AggregationInterface::MAX,
        AggregationInterface::QUANTILE,
    ];

    private static $arrayFilters = [
        FilterInterface::FILTER_IN,
        FilterInterface::FILTER_NOT_IN
    ];

    const TYPE_BOOL = 'boolean';
    const TYPE_CALLABLE = 'callable';
    const TYPE_INT = 'int';
    const TYPE_PATH = 'path';
    const TYPE_URL = 'url';
    const TYPE_STRING = 'string';

    private static $knownOptionsTypes = [
        'allow_partial' => self::TYPE_BOOL,
        'case_sensitive' => self::TYPE_BOOL,
        'filepath' => self::TYPE_PATH,
        'html_formatter' => self::TYPE_CALLABLE,
        'json_formatter' => self::TYPE_CALLABLE,
        'max_query_time' => self::TYPE_INT,
        'multiple_requests' => self::TYPE_BOOL,
        'url' => self::TYPE_URL,
        'url_generator' => self::TYPE_CALLABLE,
        'delimiter' => self::TYPE_STRING
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

        if (in_array($aggType, self::$fieldAwareAggregations)) {
            if (empty($config['field']) || !is_string($config['field'])) {
                throw new \LogicException("Invalid aggregation field");
            }
        }

        if (!isset($this->criteria[$this->currentCollection]['aggregations'])) {
            $this->criteria[$this->currentCollection]['aggregations'] = [];
        }

        $this->criteria[$this->currentCollection]['aggregations'][] = [ 'type' => $aggType ] + $config;

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

        if (isset(self::$knownOptionsTypes[$name])) {
            switch (self::$knownOptionsTypes[$name]) {
                case self::TYPE_BOOL:
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

                    if (is_bool($value) === false) {
                        throw new \LogicException("Invalid boolean");
                    }

                    break;

                case self::TYPE_CALLABLE:
                    if (is_callable($value) === false) {
                        throw new \LogicException("Invalid callable");
                    }

                    break;

                case self::TYPE_INT:
                    $value = filter_var($value, FILTER_VALIDATE_INT);

                    if (is_int($value) === false) {
                        throw new \LogicException("Invalid integer");
                    }

                    break;

                case self::TYPE_PATH:
                    break;

                case self::TYPE_URL:
                    if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                        throw new \LogicException("Invalid URL");
                    }

                    break;
            }
        }

        if (!isset($this->criteria[$this->currentCollection]['options'])) {
            $this->criteria[$this->currentCollection]['options'] = [];
        }

        $this->criteria[$this->currentCollection]['options'][$name] = $value;

        return $this;
    }

    public function setPage(int $number, $countByPage) : self
    {
        $this->setNumber('limit', $countByPage);
        $this->setNumber('offset', ($number - 1) * $countByPage);
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
