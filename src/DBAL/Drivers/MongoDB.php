<?php

namespace MKCG\Model\DBAL\Drivers;

use ArrayObject;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\AggregationInterface;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Mapper\Field;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Cursor;

class MongoDB implements DriverInterface
{
    private static $mapOptions = [
        'allow_partial' => 'allowPartialResults',
        'case_sensitive' => '$caseSensitive',
        'diacriticSensitive' => '$diacriticSensitive',
        'max_query_time' => 'maxTimeMS',
    ];

    private static $textOptions = [
        '$caseSensitive',
        '$diacriticSensitive'
    ];

    private $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getSupportedOptions() : array
    {
        return [
            // Find options
            'allow_partial',
            'batchSize',
            'max_query_time',
            'readConcern',
            'readPreference',

            // Text search
            'case_sensitive',
            'diacriticSensitive',
        ];
    }

    public static function mapArrayObject(ArrayObject $document)
    {
        $map = [];

        foreach ($document->getArrayCopy() as $key => $value) {
            $map[$key] = is_object($value) && is_a($value, ArrayObject::class)
                ? static::mapArrayObject($value)
                : $value;
        }

        return $map;
    }

    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result
    {
        $isScroll = $query->scroll !== null;

        list($collection, $filters, $criteria) = $isScroll
            ? $this->scrollSearch($query)
            : $this->makeSearchParams($query)
        ;

        foreach ($query->callableFilters as $filter) {
            $filters = call_user_func($filter, $query, $filters);
        }

        $items = array_map(
            [ MongoDB::class, 'mapArrayObject' ],
            $collection->find($filters, $criteria)->toArray()
        );

        $results = $resultBuilder->build($items, $query);

        $count = $isScroll && isset($query->scroll->data['count'])
            ? $query->scroll->data['count']
            : $collection->countDocuments($filters);

        $results->setCount($count);

        if (!empty($query->aggregations) && !$isScroll) {
            $results->setAggregations($this->aggregate($query, $collection, $filters, $count));
        }

        if ($isScroll && $count <= $query->limit + $query->offset) {
            $query->scroll->stop();
        }

        return $results;
    }

    private function aggregate(Query $query, $collection, array $filters, int $count) : array
    {
        $aggregations = [];

        foreach ($query->aggregations as $aggregation) {
            switch ($aggregation['type']) {
                case AggregationInterface::FACET:
                    if (!isset($aggregations['terms'])) {
                        $aggregations['terms'] = [];
                    }

                    $clonedQuery = clone $query;

                    if (isset($clonedQuery->filters[$aggregation['field']])) {
                        unset($clonedQuery->filters[$aggregation['field']]);
                    }

                    $aggregations['facets'][$aggregation['field']] = $this->aggregateTerms(
                        $query,
                        $collection,
                        $aggregation['field'],
                        $aggregation
                    );

                    break;

                case AggregationInterface::TERMS:
                    if (!isset($aggregations['terms'])) {
                        $aggregations['terms'] = [];
                    }

                    $aggregations['terms'][$aggregation['field']] = $this->aggregateTerms(
                        $query,
                        $collection,
                        $aggregation['field'],
                        $aggregation
                    );

                    break;

                case AggregationInterface::MIN:
                    if (!isset($aggregations['min'])) {
                        $aggregations['min'] = [];
                    }

                    $aggregations['min'][$aggregation['field']] = $this->aggregateTermPos(
                        $query,
                        $collection,
                        $aggregation['field'],
                        $aggregation,
                        1
                    );

                    break;

                case AggregationInterface::MAX:
                    if (!isset($aggregations['max'])) {
                        $aggregations['max'] = [];
                    }

                    $aggregations['max'][$aggregation['field']] = $this->aggregateTermPos(
                        $query,
                        $collection,
                        $aggregation['field'],
                        $aggregation,
                        -1
                    );

                    break;

                case AggregationInterface::QUANTILE:
                    if (!isset($aggregations['quantiles'])) {
                        $aggregations['quantiles'] = [];
                    }

                    $quantiles = [];

                    foreach ($aggregation['quantile'] as $quantile) {
                        $quantiles[] = $this->aggregateTermPos(
                            $query,
                            $collection,
                            $aggregation['field'],
                            $aggregation,
                            1,
                            $quantile / 100 * $count
                        );
                    }

                    $aggregations['quantiles'][$aggregation['field']] = $quantiles;

                    break;

                case AggregationInterface::AVERAGE:
                    if (!isset($aggregations['average'])) {
                        $aggregations['average'] = [];
                    }

                    $aggregations['average'][$aggregation['field']] = $this->aggregateAverage(
                        $query,
                        $collection,
                        $aggregation['field'],
                        $aggregation
                    );

                    break;

                default:
                    throw new \Exception("Aggregation type not supported : " . $aggregation['type']);
            }
        }

        return $aggregations;
    }

    private function aggregateAverage(Query $query, $collection, string $field, array $config)
    {
        $options = $this->makeOptions($query->context ?? []);
        $textOptions = array_intersect_key($options, array_fill_keys(self::$textOptions, null));
        $searchOptions = array_diff_key($options, array_fill_keys(self::$textOptions, null));
        $filters = $this->makeFilters($query, $textOptions);

        $pipeline = [
            [ '$match' => [ $field => [ '$exists' => true ] ] ],
            [ '$group' => [ '_id'=> null, 'average' => [ '$avg' => '$' . $field ] ] ],
        ];

        if (!empty($filters)) {
            array_unshift($pipeline, ['$match' => $filters]);
        }

        $type = $query->schema->getFieldType($field);

        $result = $collection->aggregate($pipeline)->toArray();
        $result = array_column($result, 'average');
        $result = array_shift($result);

        return isset($config['decimal'])
            ? round($result, $config['decimal'])
            : $result;
    }

    private function aggregateTerms(Query $query, $collection, string $field, array $aggregation)
    {
        $options = $this->makeOptions($query->context ?? []);
        $textOptions = array_intersect_key($options, array_fill_keys(self::$textOptions, null));
        $searchOptions = array_diff_key($options, array_fill_keys(self::$textOptions, null));
        $filters = $this->makeFilters($query, $textOptions);

        $pipeline = [
            [ '$group' => [ '_id'=> '$' . $field, 'count' => [ '$sum' => 1 ] ] ],
            [ '$sort' => [ 'count' => -1 ] ],
            [ '$skip' => $aggregation['offset'] ?? 0 ],
            [ '$limit' => $aggregation['limit'] ?? 10 ]
        ];

        if (!empty($filters)) {
            array_unshift($pipeline, ['$match' => $filters]);
        }

        $type = $query->schema->getFieldType($field);

        return array_map(function($item) use ($type, $field) {
            return [
                'name' => Field::formatValue($type, $field, $item['_id']),
                'count' => (int) $item['count']
            ];
        }, $collection->aggregate($pipeline)->toArray());
    }

    private function aggregateTermPos(Query $query, $collection, string $field, array $aggregation, int $order, int $skip = 0)
    {
        $options = $this->makeOptions($query->context ?? []);
        $textOptions = array_intersect_key($options, array_fill_keys(self::$textOptions, null));
        $searchOptions = array_diff_key($options, array_fill_keys(self::$textOptions, null));
        $filters = $this->makeFilters($query, $textOptions);

        $pipeline = [
            [ '$group' => [ '_id'=> '$' . $field ] ],
            [ '$sort' => [ '_id' => $order ] ],
            [ '$skip' => $skip ],
            [ '$limit' => 1 ]
        ];

        if (!empty($filters)) {
            array_unshift($pipeline, ['$match' => $filters]);
        }

        $type = $query->schema->getFieldType($field);

        $values = array_map(function($item) {
            return $item['_id'];
        }, $collection->aggregate($pipeline)->toArray());

        $value = array_shift($values);
        return Field::formatValue($type, $field, $value);
    }

    private function scrollSearch(Query $query) : array
    {
        if (!isset($query->scroll->data['last_query'])) {
            $query->scroll->data['last_query'] = $this->makeSearchParams($query);
        }

        list($collection, $filters, $criteria) = $query->scroll->data['last_query'];
        $criteria['skip'] = $query->offset;
        $criteria['limit'] = $query->limit;

        return [$collection, $filters, $criteria];
    }

    private function makeSearchParams(Query $query) : array
    {
        list($database, $collectionName) = explode('.', $query->name);
        $collection = $this->client->selectCollection($database, $collectionName);

        $options = $this->makeOptions($query->context ?? []);
        $textOptions = array_intersect_key($options, array_fill_keys(self::$textOptions, null));
        $searchOptions = array_diff_key($options, array_fill_keys(self::$textOptions, null));

        $filters = $this->makeFilters($query, $textOptions);

        $criteria = [
            'projection' => array_fill_keys($this->project($query->fields), 1),
            'limit' => $query->limit,
            'sort' => $this->makeSort($query),
            'skip' => $query->offset,
        ] + $searchOptions;

        return [$collection, $filters, $criteria];
    }

    private function makeFilters(Query $query, array $textOptions) : array
    {
        $filters = [];

        foreach ($query->filters as $field => $fieldFilters) {
            if (!isset($filters[$field])) {
                $filters[$field] = [];
            }

            foreach ($fieldFilters as $type => $value) {
                switch ($type) {
                    case FilterInterface::FILTER_IN:
                    case FilterInterface::FILTER_NOT_IN:
                        if (!is_array($value)) {
                            $value = [ $value ];
                        }

                        break;
                }

                switch ($type) {
                    case FilterInterface::FILTER_IN:
                        $filters[$field]['$in'] = array_values($value);
                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        $filters[$field]['$nin'] = array_values($value);
                        break;

                    case FilterInterface::FILTER_GREATER_THAN:
                        $filters[$field]['$gt'] = $value;
                        break;

                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                        $filters[$field]['$gte'] = $value;
                        break;

                    case FilterInterface::FILTER_LESS_THAN:
                        $filters[$field]['$lt'] = $value;
                        break;

                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                        $filters[$field]['$lte'] = $value;
                        break;

                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        $filters['$text'] = [
                            '$search' => $value,
                            '$language' => 'none',
                        ] + $textOptions;

                        break;

                    default:
                        throw new \Exception("Filter not supported : " . $type);
                }
            }

            if ($filters[$field] === []) {
                unset($filters[$field]);
            }
        }

        return $filters;
    }

    private function project(array $fields) : array
    {
        $projection = [];

        foreach ($fields as $key => $value) {
            if (is_string($value)) {
                $projection[] = $value;
            } else if (is_array($value)) {
                foreach ($this->project($value) as $subfield) {
                    $projection[] = $key . '.' . $subfield;
                }
            } else {
                throw new \Exception("Invalid field");
            }
        }

        return $projection;
    }

    private function makeSort(Query $query) : array
    {
        $sort = [];

        foreach ($query->sort as $fieldSort) {
            $sort[$fieldSort[0]] = $fieldSort[1] === 'ASC'
                ? 1
                : -1;
        }

        return $sort;
    }

    private function makeOptions(array $context) : array
    {
        if (empty($context['options'])) {
            return [];
        }

        $options = [];

        foreach ($this->getSupportedOptions() as $option) {
            if (!isset($context['options'][$option])) {
                continue;
            }

            $value = $context['options'][$option];

            if (isset(self::$mapOptions[$option])) {
                $option = self::$mapOptions[$option];
            }

            $options[$option] = $value;
        }

        return $options;
    }
}
