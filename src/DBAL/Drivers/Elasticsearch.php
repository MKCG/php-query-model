<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

abstract class Elasticsearch implements DriverInterface
{
    private $client;
    private $timeout;
    private $terminateAfter;

    public function __construct(ClientInterface $client, int $timeout = 0, int $terminateAfter = 0)
    {
        $this->client = $client;
        $this->timeout = $timeout;
        $this->terminateAfter = $terminateAfter;
    }

    public function getSupportedOptions() : array
    {
        return [];
    }

    abstract protected function makeHttpRequest(string $collection, array $payload) : RequestInterface;

    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result
    {
        if ($query->limitByParent > 0) {
            // @todo : use Field Collapsing
            // @see : https://www.elastic.co/guide/en/elasticsearch/reference/7.x/search-request-body.html#request-body-search-collapse
            throw new \LogicException("limitByParent not supported yet");
        }

        $search = $this->makeSearch($query);
        $filters = $this->makeFilters($query);

        if (!empty($filters['filters']) || !empty($filters['must_not'])) {
            $search['query'] = [ 'bool' => $filters ];
        }

        $request = $this->makeHttpRequest($query->name, $search);
        $response = $this->client->sendRequest($request);

        if ($request->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \Exception("Invalid response");
        }

        $content = $response->getBody();
        $content = json_decode($content, JSON_OBJECT_AS_ARRAY);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response");
        }

        $elements = array_map(function($hit) {
            return $hit['_source'];
        }, $content['hits'] ?? []);

        $result = $resultBuilder->build($elements, $query);

        if (isset($hit['total'])) {
            if (is_array($hit['total'])) {
                $result->setCount((int) ($hit['total']['value'] ?? 0));
            } else {
                $result->setCount((int) $hit['total']);
            }
        }

        return $result;
    }

    private function makeSearch(Query $query)
    {
        $search = [
            '_source' => $query->fields
        ];

        if (!empty($query->sort)) {
            $search['sort'] = [];

            foreach ($query->sort as $sort) {
                $search['sort'][] = [ $sort[0] => $sort[1] ];
            }
        }

        if ($query->offset > 0) {
            $search['from'] = $query->offset;
        }

        if ($query->limit > 0) {
            $search['size'] = $query->limit;
        }

        if ($this->timeout > 0) {
            $search['timeout'] = $this->timeout;
        }

        if ($this->terminateAfter > 0) {
            $search['terminate_after'] = $this->terminateAfter;
        }

        return $search;
    }

    private function makeFilters(Query $query)
    {
        $filters = [];
        $ranges = [];
        $mustNot = [];

        $pushRange = function(string $field, string $type, $value) use (&$ranges) {
            if (isset($ranges[$field])) {
                // @todo : handle the case when the $type is already defined
                $ranges[$field]['range'][$field][$type] = $value;
            } else {
                $ranges[$field] = $this->makeRangeFilter($field, $type, $value);
            }
        };

        foreach ($query->filters as $field => $value) {
            if (!is_array($value)) {
                // @todo : use match filter for text fields
                $filters[] = $this->makeTermFilter($field, $value);
                continue;
            }

            if (array_keys($value) === range(0, count($value) - 1)) {
                // @todo : use match filter for text fields
                $filters[] = $this->makeTermsFilter($field, $value);
                continue;
            }

            foreach ($value as $filterType => $filterValue) {
                switch(strtolower($filterType)) {
                    case FilterInterface::FILTER_IN:
                        $filters[] = is_array($filterValue)
                            ? $this->makeTermsFilter($field, $filterValue)
                            : $this->makeTermFilter($field, $filterValue)
                        ;

                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        $mustNot[] = is_array($filterValue)
                            ? $this->makeTermsFilter($field, $filterValue)
                            : $this->makeTermFilter($field, $filterValue)
                        ;

                        break;

                    case FilterInterface::FILTER_LESS_THAN:
                        $pushRange($field, 'lt', $filterValue);
                        break;

                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                        $pushRange($field, 'lte', $filterValue);
                        break;

                    case FilterInterface::FILTER_GREATER_THAN:
                        $pushRange($field, 'gt', $filterValue);
                        break;

                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                        $pushRange($field, 'gte', $filterValue);
                        break;

                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        $filters[] = $this->makeFilterMatch($field, $filterValue);
                        break;

                    default:
                        throw new \LogicException("Filter type not supported : ". $filterType);
                }
            }
        }

        if (!empty($ranges)) {
            $filters[] = array_values($ranges);
        }

        return array_filter([ 'filter' => $filters, 'must_not' => $mustNot ]);
    }

    private function makeFilterMatch(string $field, $value) : array
    {
        return [ 'match' => [ $field => [ 'query' => $value ] ] ];
    }

    private function makeTermFilter(string $field, $value) : array
    {
        return ['term' => [ $field => [ 'value' => $value ] ] ];
    }

    private function makeTermsFilter(string $field, array $values) : array
    {
        return ['terms' => [ $field => $values ] ];
    }

    private function makeFilterRange(string $field, string $type, $value) : array
    {
        return [ 'range' => [ $field => [ $type => $value ] ] ];
    }
}
