<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\FilterInterface;

use Ehann\RedisRaw\RedisRawClientInterface;
use Ehann\RediSearch\Query\Builder;

class Redisearch implements DriverInterface
{
    private static $numericPrecision = 0.000001;
    private $client;

    public function __construct(RedisRawClientInterface $client)
    {
        $this->client = $client;
    }

    public function getSupportedOptions() : array
    {
        return [];
    }

    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result
    {
        $queryBuilder = (new Builder($this->client, $query->name))
            ->return($query->fields)
            ->limit($query->offset, $query->limit);

        foreach ($query->sort as $sort) {
            $queryBuilder->sortBy($sort[0], $sort[1]);
        }

        $search = '*';

        if (!empty($query->filters)) {
            $search = $this->applyFilters($queryBuilder, $query);
        }

        $redisResult = $queryBuilder->search($search, true);
        $result = $resultBuilder->build($redisResult->getDocuments(), $query);
        $result->setCount($redisResult->getCount());

        return $result;
    }

    private function applyFilters(Builder $queryBuilder, Query $query)
    {
        $search = [];
        $notIn = [];

        foreach ($query->filters as $field => $filter) {
            foreach ($filter as $type => $value) {
                if (in_array($type, [FilterInterface::FILTER_IN, FilterInterface::FILTER_NOT_IN]) && !is_array($value)) {
                    $value = [ $value ];
                }

                if (in_array($type, [FilterInterface::FILTER_GREATER_THAN, FilterInterface::FILTER_LESS_THAN])) {
                    $value = '(' . $value;
                }

                switch ($type) {
                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        $search[] = sprintf("@%s:%s", $this->escape($field), $this->escape($value));
                        break;

                    case FilterInterface::FILTER_GREATER_THAN:
                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                        $search[] = sprintf("@%s:[%f +inf]", $this->escape($field), $this->escape($value));
                        break;

                    case FilterInterface::FILTER_LESS_THAN:
                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                        $search[] = sprintf("@%s:[-inf %f]", $this->escape($field), $this->escape($value));
                        break;

                    case FilterInterface::FILTER_IN:
                        $search[] = sprintf(
                            '@%s:{%s}',
                            $this->escape($field),
                            implode('|', array_map([$this, 'escape'], $value))
                        );
                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        $notIn[] = sprintf(
                            '-@%s:{%s}',
                            $this->escape($field),
                            implode('|', array_map([$this, 'escape'], $value))
                        );
                        break;
                }
            }
        }

        return trim(implode(' ', $search) . ' ' . implode(' ', $notIn));
    }

    private function escape(string $text) : string
    {
        return str_replace(['-', '@', ':'], ['\-', '\@', '\:'], $text);
    }
}
