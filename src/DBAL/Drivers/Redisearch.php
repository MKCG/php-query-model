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
                case AggregationInterface::TERMS:
                    if (!isset($agg['terms'])) {
                        $agg['terms'] = [];
                    }

                    $agg['terms'][$config['field']] = $this->makeTermsAgg($query, $config['field'], $config, $search);
                    break;

                case AggregationInterface::FACET:
                    if (!isset($agg['facets'])) {
                        $agg['facets'] = [];
                    }

                    $agg['facets'][$config['field']] = $this->makeFacet(clone $query, $config['field'], $config, $search);
                    break;

                case AggregationInterface::AVERAGE:
                    if (!isset($agg['averages'])) {
                        $agg['averages'] = [];
                    }

                    $agg['averages'][$config['field']] = $this->makeAverage($query, $config['field'], $config, $search);
                    break;

                case AggregationInterface::MIN:
                    if (!isset($agg['min'])) {
                        $agg['min'] = [];
                    }

                    $agg['min'][$config['field']] = $this->makeMin($query, $config['field'], $config, $search);
                    break;

                case AggregationInterface::MAX:
                    if (!isset($agg['max'])) {
                        $agg['max'] = [];
                    }

                    $agg['max'][$config['field']] = $this->makeMax($query, $config['field'], $config, $search);
                    break;

                case AggregationInterface::QUANTILE:
                    if (!isset($agg['quantiles'])) {
                        $agg['quantiles'] = [];
                    }

                    $agg['quantiles'][$config['field']] = $this->makeQuantiles($query, $config['field'], $config, $search);
                    break;

                default:
                    throw new \Exception("Aggregation not supported : " . $config['type']);
            }
        }

        return $agg;
    }

    private function makeQuantiles(Query $query, string $field, array $config, string $search) : array
    {
        if (!isset($config['quantile'])) {
            throw new \Exception("'quantile' configuration is missing");
        }

        $values = is_array($config['quantile'])
            ? $config['quantile']
            : [ $config['quantile'] ];

        $quantiles = [];
        $type = $query->schema->getFieldType($field);

        foreach ($values as $value) {
            $result = $this->makeBuilderAggGroupByUnknown($query)
                ->quantile($field, $value / 100)
                ->search($search, true);

            $result = current(array_column($result->getDocuments(), 'quantile_' . $field));

            $quantiles[] = Field::formatValue($type, $field, $result);
        }

        return $quantiles;
    }

    private function makeAverage(Query $query, string $field, array $config, string $search) : float
    {
        $result = $this->makeBuilderAggGroupByUnknown($query)
            ->avg($field)
            ->search($search, true);

        $result = (float) current(array_column($result->getDocuments(), 'avg_' . $field));

        if (isset($config['decimal'])) {
            $result = round($result, $config['decimal']);
        }

        return $result;
    }

    private function makeMin(Query $query, string $field, array $config, string $search) : float
    {
        $result = $this->makeBuilderAggGroupByUnknown($query)
            ->min($field)
            ->search($search, true);

        return (float) current(array_column($result->getDocuments(), 'min_' . $field));
    }

    private function makeMax(Query $query, string $field, array $config, string $search) : float
    {
        $result = $this->makeBuilderAggGroupByUnknown($query)
            ->max($field)
            ->search($search, true);

        return (float) current(array_column($result->getDocuments(), 'max_' . $field));
    }

    private function makeBuilderAggGroupByUnknown(Query $query)
    {
        $fields = $query->schema->getFields('');

        // Generate an invalid field
        $groupBy = 'aqwzsx';

        while (in_array($groupBy, $fields) || !empty($query->schema->getFieldType($groupBy))) {
            $groupBy = substr(md5($groupBy), 0, 8);
        }

        return (new Index($this->client))
            ->setIndexName($query->name)
            ->makeAggregateBuilder()
            ->groupBy($groupBy);
    }

    private function makeFacet(Query $query, string $field, array $config, string $search)
    {
        if (isset($query->filters[$field])) {
            unset($query->filters[$field]);

            $search = !empty($query->filters)
                ? $this->applyFilters($query)
                : '*';
        }

        return $this->makeTermsAgg($query, $field, $config, $search);
    }

    private function makeTermsAgg(Query $query, string $field, array $config, string $search)
    {
        try {
            $terms = (new Index($this->client))
                ->setIndexName($query->name)
                ->makeAggregateBuilder()
                ->load([$field])
                ->apply("split(@" . $field . ")", $field)
                ->groupBy($field)
                ->count()
                ->sortBy('count', false)
                ->limit($config['offset'] ?: 0, $config['limit'] ?: 10)
                ->search($search, true);
        } catch (\Exception $e) {
            return [];
        }

        $type = $query->schema->getFieldType($field);

        return array_map(function($value) use ($field, $type) {
            return [
                'name' => Field::formatValue($type, $field, $value[$field]),
                'count' => (int) $value['count']
            ];
        }, $terms->getDocuments());
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
                        $wildcard = $value[strlen($value) - 1] !== ' ';
                        $values = explode(' ', $value);
                        $values = array_map('trim', $values);
                        $values = array_map([$this, 'escape'], $values);
                        $last = array_pop($values) . ($wildcard ? '*' : '');

                        $values = array_map(function($value) {
                            if (strlen($value) < 4) {
                                return $value . '*';
                            }
                            return strlen($value) < 6
                                ? '%' . $value . '%'
                                : (strlen($value) < 8
                                    ? '%%' . $value . '%%'
                                    : '%%%' . $value . '%%%'
                                );
                        }, $values);

                        array_push($values, $last);

                        $search[] = '@' . $this->escape($field) . ':' . implode(' ', $values);
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
            ['-',  '@',  ':',  '(',  ')',  '{',  '}',  '|', '%' , '/' ],
            ['\-', '\@', '\:', '\(', '\)', '\{', '\}', '|', '\%', '\/'],
            $text
        );
    }
}
