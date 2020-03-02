<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\FieldInterface;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\AggregationInterface;
use MKCG\Model\DBAL\Mapper\Field;

use Ehann\RedisRaw\RedisRawClientInterface;
use Ehann\RediSearch\Query\Builder;
use Ehann\RediSearch\Index;

class Redisearch implements DriverInterface
{
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
        $queryBuilder = (new Index($this->client))
            ->setIndexName($query->name)
            ->return($query->fields)
            ->limit($query->offset, $query->limit);

        foreach ($query->sort as $sort) {
            $queryBuilder->sortBy($sort[0], $sort[1]);
        }

        $search = !empty($query->filters)
            ? $this->applyFilters($query)
            : '*';

        $redisResult = $queryBuilder->search($search, true);
        $result = $resultBuilder->build($redisResult->getDocuments(), $query);
        $result->setCount($redisResult->getCount());

        if (!empty($query->aggregations)) {
            $aggregations = $this->aggregate($query, $search);
            $result->setAggregations($aggregations);
        }

        if ($query->scroll !== null) {
            if (!isset($query->scroll->data['totalLimit'])) {
                $query->scroll->data['totalLimit'] = $query->limit;
            } else {
                $query->scroll->data['totalLimit'] += $query->limit;
            }

            if ($query->scroll->data['totalLimit'] >= $redisResult->getCount()) {
                $query->scroll->stop();
            }
        }

        return $result;
    }

    private function aggregate(Query $query, string $search)
    {
        $agg = [];

        foreach ($query->aggregations as $config) {
            switch ($config['type']) {
                case AggregationInterface::AGG_FACET:
                    if (!isset($agg['facets'])) {
                        $agg['facets'] = [];
                    }

                    $agg['facets'][$config['field']] = $this->makeFacet(clone $query, $config['field'], $config, $search);
                    break;

                case AggregationInterface::AGG_AVERAGE:
                    if (!isset($agg['averages'])) {
                        $agg['averages'] = [];
                    }

                    $agg['averages'][$config['field']] = $this->makeAverage(clone $query, $config['field'], $config, $search);
                    break;

                default:
                    throw new \Exception("Aggregation not supported : " . $config['type']);
            }
        }

        return $agg;
    }

    private function makeAverage(Query $query, string $field, array $config, string $search)
    {
        $fields = $query->schema->getFields('');

        // Generate an invalid field
        $groupBy = 'aqwzsx';

        while (in_array($groupBy, $fields) || !empty($query->schema->getFieldType($groupBy))) {
            $groupBy = substr(md5($groupBy), 0, 8);
        }

        $avg = (new Index($this->client))
            ->setIndexName($query->name)
            ->makeAggregateBuilder()
            ->groupBy('id')
            ->avg($field)
            ->search($search, true);

        $avg = (float) current(array_column($avg->getDocuments(), 'avg_' . $field));

        if (isset($config['decimal'])) {
            $avg = round($avg, $config['decimal']);
        }

        return $avg;
    }

    private function makeFacet(Query $query, string $field, array $config, string $search)
    {
        if (isset($query->filters[$field])) {
            unset($query->filters[$field]);

            $search = !empty($query->filters)
                ? $this->applyFilters($query)
                : '*';
        }

        $facet = (new Index($this->client))
            ->setIndexName($query->name)
            ->makeAggregateBuilder()
            ->groupBy($field)
            ->count()
            ->sortBy('count', false)
            ->limit($config['offset'] ?: 0, $config['limit'] ?: 10)
            ->search($search, true)
        ;

        $type = $query->schema->getFieldType($field);

        return array_map(function($value) use ($field, $type) {
            return [
                'name' => Field::formatValue($type, $field, $value[$field]),
                'count' => (int) $value['count']
            ];
        }, $facet->getDocuments());
    }

    private function applyFilters(Query $query)
    {
        $search = [];
        $notIn = [];

        foreach ($query->filters as $field => $filter) {
            foreach ($filter as $type => $value) {
                $this->assertFilterable($query, $field, $type);

                if (in_array($type, [FilterInterface::FILTER_IN, FilterInterface::FILTER_NOT_IN]) && !is_array($value)) {
                    $value = [ $value ];
                }

                switch ($type) {
                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        $search[] = sprintf("@%s:%s", $this->escape($field), $this->escape($value));
                        break;

                    case FilterInterface::FILTER_GREATER_THAN:
                        $search[] = sprintf("@%s:[(%f +inf]", $this->escape($field), (float) $value);
                        break;

                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                        $search[] = sprintf("@%s:[%f +inf]", $this->escape($field), (float) $value);
                        break;

                    case FilterInterface::FILTER_LESS_THAN:
                        $search[] = sprintf("@%s:[-inf (%f]", $this->escape($field), (float) $value);
                        break;

                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                        $search[] = sprintf("@%s:[-inf %f]", $this->escape($field), (float) $value);
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

    private function assertFilterable(Query $query, string $field, string $filterType)
    {
        if (!$query->schema->isFilterable($field)) {
            throw new \Exception("Field is not filterable : " . $field);
        }

        $fieldType = $query->schema->getFieldType($field);

        if (in_array($filterType, FilterInterface::RANGE_FILTERS)) {
            if (!in_array($fieldType, FieldInterface::NUMERIC_TYPES)) {
                throw new \Exception("Range filters operations can not be applied to : " . $field);
            }
        } else if (in_array($filterType, FilterInterface::SET_FILTERS)) {
            if ($fieldType !== FieldInterface::TYPE_ENUM) {
                throw new \Exception("Tag filters can not be applied to : " . $field);
            }
        }
    }

    private function escape(string $text) : string
    {
        return str_replace(
            ['-',  '@',  ':',  '(',  ')',  '{',  '}',  '|', '%' ],
            ['\-', '\@', '\:', '\(', '\)', '\{', '\}', '|', '\%'],
            $text
        );
    }
}
