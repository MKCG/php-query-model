<?php

namespace MKCG\Model\DBAL\Drivers;

use ArrayObject;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;

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

    public function search(Query $query) : Result
    {
        $isScroll = !empty($query->context['scroll']);

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

        $results = Result::make($items, $query->entityClass);

        $count = $isScroll && isset($query->context['scroll']->data['count'])
            ? $query->context['scroll']->data['count']
            : $collection->countDocuments($filters);

        $results->setCount($count);

        if ($isScroll && $count <= $query->limit + $query->offset) {
            $query->context['scroll']->data['end'] = true;
        }

        return $results;
    }

    private function scrollSearch(Query $query) : array
    {
        if (!isset($query->context['scroll']->data['last_query'])) {
            $query->context['scroll']->data['last_query'] = $this->makeSearchParams($query);
        }

        list($collection, $filters, $criteria) = $query->context['scroll']->data['last_query'];
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
                        $filters[$field]['$in'] = $value;
                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        $filters[$field]['$nin'] = $value;
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
