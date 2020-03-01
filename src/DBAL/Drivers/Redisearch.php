<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;

use Ehann\RedisRaw\RedisRawClientInterface;
use Ehann\RediSearch\Query\Builder;

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
        $queryBuilder = (new Builder($this->client, $query->name))
            ->return($query->fields);

        $this->applyFilters($queryBuilder, $query);

        if ($query->limit > 0) {
            $queryBuilder->limit($query->offset, $query->limit);
        }

        foreach ($query->sort as $sort) {
            $queryBuilder->sortBy($sort[0], $sort[1]);
        }

        $redisResult = $queryBuilder->search('', true);
        $result = $resultBuilder->build($redisResult->getDocuments(), $query);
        $result->setCount($redisResult->getCount());

        return $result;
    }

    private function applyFilters(Builder $queryBuilder, Query $query)
    {
        if (!empty($query->filters)) {
            throw new \Exception("Filters not supported yet");
        }
    }
}
