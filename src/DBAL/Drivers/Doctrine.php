<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\FieldInterface;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\AggregationInterface;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Mapper\Field;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Query\QueryBuilder;

class Doctrine implements DriverInterface
{
    private $connection;
    private $reservedWords;

    public function __construct(Connection $connection, array $reservedWords = ['key', 'value'])
    {
        $this->connection = $connection;
        $this->reservedWords = array_map('strtolower', $reservedWords);
    }

    public function getSupportedOptions() : array
    {
        return [];
    }

    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result
    {
        if (empty($query->name)) {
            throw new \LogicException("Table must be defined");
        }

        $queryBuilder = $this->connection
            ->createQueryBuilder()
            ->from($query->name)
        ;

        $this
            ->selectFields($queryBuilder, $query)
            ->applyFilters($queryBuilder, $query, $query->filters);

        foreach ($query->sort as $sort) {
            $queryBuilder->addOrderBy($sort[0], $sort[1]);
        }

        if ($query->limitByParent > 0) {
            $ids = $this->listIdsForeachParent($query, clone $queryBuilder);

            if (empty($ids)) {
                return $resultBuilder->build([], $query);
            }

            $this->applyFilterIn($queryBuilder, current($query->primaryKeys), $ids, $query);
        } else {
            if ($query->limit > 0) {
                $queryBuilder->setMaxResults($query->limit);
            }

            if ($query->offset > 0) {
                $queryBuilder->setFirstResult($query->offset);
            }
        }

        $content = $queryBuilder->execute()->fetchAll();
        $result = $resultBuilder->build($content, $query);

        if ($query->scroll === null) {
            $count = $this->count(clone $queryBuilder);
            $result->setCount($count);
            $this->makeAggregations($result, $query, $queryBuilder);
        } else if (count($content) === 0) {
            $query->scroll->stop();
        }

        return $result;
    }

    private function listIdsForeachParent(
        Query $query,
        QueryBuilder $innerQueryBuilder
    ) {
        if (count($query->primaryKeys) !== 1) {
            throw new \LogicException("limitByParent can only be applied when there is only one primaryKey");
        }

        if (!isset($query->context['parent_ref'])) {
            throw new \Exception("The 'parent_ref' context is missing from the query");
        }

        $innerQueryBuilder->select([$query->context['parent_ref'] , current($query->primaryKeys) ]);

        $innerSQL = $innerQueryBuilder->getSQL();
        $escapedFieldName = $this->escapeFieldName($query->context['parent_ref']);

        $sql = sprintf(
            "
            SELECT
                GROUP_CONCAT(ordered_items_by_ref) AS ordered_items_list
            FROM (
                SELECT
                    %s,
                    SUBSTRING_INDEX(SUBSTRING_INDEX(ordered_items, ',', %d), ',', %d) as ordered_items_by_ref
                FROM (
                    SELECT
                        %s,
                        GROUP_CONCAT(%s SEPARATOR ',') as ordered_items
                    FROM (
                        %s
                    ) AS T
                    GROUP BY
                        %s
                ) AS T2
            ) AS T3",
            $escapedFieldName,
            $query->limitByParent + $query->offset,
            -$query->limitByParent,
            $escapedFieldName,
            $this->escapeFieldName(current($query->primaryKeys)),
            $innerSQL,
            $escapedFieldName
        );

        foreach ($innerQueryBuilder->getParameters() as $parameter => $value) {
            if (is_array($value)) {
                $value = array_map(function($value) {
                    return is_string($value)
                        ? $this->connection->quote($value)
                        : $value;
                }, $value);
                $value = implode(', ', $value);

                $sql = str_replace(':' . $parameter, $value, $sql);
            } else {
                $sql = str_replace(':' . $parameter, $value, $sql);
            }
        }

        $statement = $this->connection->prepare($sql);

        if ($statement->execute() === false) {
            throw new \Exception("Error Processing Request");
        }

        $result = $statement->fetch();

        if ($result === false) {
            throw new \Exception("Error Processing Request");
        }

        $ids = explode(',', $result['ordered_items_list']);
        return $ids;
    }

    private function count(QueryBuilder $queryBuilder)
    {
        return (int) $queryBuilder
            ->select('count(1)')
            ->setFirstResult(null)
            ->setMaxResults(null)
            ->execute()
            ->fetch(FetchMode::COLUMN);
    }

    private function selectFields(QueryBuilder $queryBuilder, Query $query)
    {
        $fields = array_merge($query->fields, $query->primaryKeys);
        $fields = array_unique($fields);
        $fields = array_map(function($field) {
            return $this->escapeFieldName($field);
        }, $fields);

        $queryBuilder->select($fields ?: '*');

        return $this;
    }

    private function applyFilters(QueryBuilder $queryBuilder, Query $query, array $filters)
    {
        foreach ($filters as $field => $value) {
            if (!is_array($value)) {
                $this->applyFilterIn($queryBuilder, $field, [ $value ], $query);
                continue;
            }

            if (array_keys($value) === range(0, count($value) - 1)) {
                $this->applyFilterIn($queryBuilder, $field, $value, $query);
                continue;
            }

            foreach ($value as $filterType => $filterValue) {
                switch(strtolower($filterType)) {
                    case FilterInterface::FILTER_IN:
                        !is_array($filterValue) and $filterValue = [ $filterValue ];
                        $this->applyFilterIn($queryBuilder, $field, $filterValue, $query);
                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        !is_array($filterValue) and $filterValue = [ $filterValue ];
                        $this->applyFilterNotIn($queryBuilder, $field, $filterValue, $query);
                        break;

                    case FilterInterface::FILTER_LESS_THAN:
                        $this->applyFilterLessThan($queryBuilder, $field, $filterValue);
                        break;

                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                        $this->applyFilterLessThanEqual($queryBuilder, $field, $filterValue);
                        break;

                    case FilterInterface::FILTER_GREATER_THAN:
                        $this->applyFilterGreaterThan($queryBuilder, $field, $filterValue);
                        break;

                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                        $this->applyFilterGreaterThanEqual($queryBuilder, $field, $filterValue);
                        break;

                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        $this->applyFilterLike($queryBuilder, $field, $filterValue);
                        break;

                    default:
                        throw new \LogicException("Filter type not supported : ". $filterType);
                }
            }
        }

        foreach ($query->callableFilters as $filter) {
            call_user_func($filter, $query, $queryBuilder);
        }

        return $this;
    }

    private function applyFilterIn(QueryBuilder $queryBuilder, string $field, array $value, Query $query)
    {
        $paramName = 'FILTER_IN_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->in($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, $value, $this->getDoctrineParamType($field, $query));
    }

    private function applyFilterNotIn(QueryBuilder $queryBuilder, string $field, array $value, Query $query)
    {
        $paramName = 'FILTER_NOT_IN_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->notIn($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, $value, $this->getDoctrineParamType($field, $query));
    }

    private function applyFilterGreaterThanEqual(QueryBuilder $queryBuilder, string $field, $value)
    {
        $paramName = 'FILTER_GTE_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->gte($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, $value);
    }

    private function applyFilterGreaterThan(QueryBuilder $queryBuilder, string $field, $value)
    {
        $paramName = 'FILTER_GT_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->gt($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, $value);
    }

    private function applyFilterLessThanEqual(QueryBuilder $queryBuilder, string $field, $value)
    {
        $paramName = 'FILTER_LTE_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->lte($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, $value);
    }

    private function applyFilterLessThan(QueryBuilder $queryBuilder, string $field, $value)
    {
        $paramName = 'FILTER_LT_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->lt($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, $value);
    }

    private function applyFilterLike(QueryBuilder $queryBuilder, string $field, $value)
    {
        $paramName = 'FILTER_LIKE_' . $field;
        $field = $this->escapeFieldName($field);
        $queryBuilder->andWhere($queryBuilder->expr()->like($field, ':' . $paramName));
        $queryBuilder->setParameter($paramName, '%' . $value . '%');
    }

    private function escapeFieldName(string $field) : string
    {
        return in_array(strtolower($field), $this->reservedWords)
            ? '`' . $field . '`'
            : $field;
    }

    private function getDoctrineParamType(string $field, Query $query) : int
    {
        $type = $query->schema->getFieldType($field);

        return in_array($type, [ FieldInterface::TYPE_INT, FieldInterface::TYPE_FLOAT ], true)
            ? Connection::PARAM_INT_ARRAY
            : Connection::PARAM_STR_ARRAY
        ;
    }

    private function makeAggregations(Result $result, Query $query, QueryBuilder $queryBuilder)
    {
        if (empty($query->aggregations)) {
            return $this;
        }

        $aggregations = [];

        foreach ($query->aggregations as $config) {
            $escapedFieldName = $this->escapeFieldName($config['field']);

            switch ($config['type']) {
                case AggregationInterface::AVERAGE:
                    if (!isset($aggregations['averages'])) {
                        $aggregations['averages'] = [];
                    }

                    $aggregations['averages'][$config['field']] = $this->aggByFunction($queryBuilder, $escapedFieldName, 'AVG');
                    break;

                case AggregationInterface::MIN:
                    if (!isset($aggregations['min'])) {
                        $aggregations['min'] = [];
                    }

                    $aggregations['min'][$config['field']] = $this->aggByFunction($queryBuilder, $escapedFieldName, 'MIN');
                    break;

                case AggregationInterface::MAX:
                    if (!isset($aggregations['max'])) {
                        $aggregations['max'] = [];
                    }

                    $aggregations['max'][$config['field']] = $this->aggByFunction($queryBuilder, $escapedFieldName, 'MAX');
                    break;

                case AggregationInterface::QUANTILE:
                    if (!isset($aggregations['quantiles'])) {
                        $aggregations['quantiles'] = [];
                    }

                    $aggregations['quantiles'][$config['field']] = array_map(function($quantile) use ($result, $queryBuilder, $config) {
                        $builder = clone $queryBuilder;
                        $builder->select($escapedFieldName);
                        $builder->setFirstResult((int) ($result->getCount() * $quantile / 100));
                        $builder->setMaxResults(1);
                        $value = $builder->execute()->fetch();

                        return isset($value[$config['field']])
                            ? $value[$config['field']]
                            : null;
                    }, $config['quantile']);
                    break;

                case AggregationInterface::TERMS:
                    if (!isset($aggregations['terms'])) {
                        $aggregations['terms'] = [];
                    }

                    $aggregations['terms'][$config['field']] = $this->aggregateByTerms(
                        $query,
                        clone $queryBuilder,
                        $config['field'],
                        $config['limit'] ?? 10,
                        $config['offset'] ?? 0
                    );

                    break;

                case AggregationInterface::FACET:
                    if (!isset($aggregations['facets'])) {
                        $aggregations['facets'] = [];
                    }

                    $aggregations['facets'][$config['field']] = $this->aggregateByFacet(
                        $query,
                        $config['field'],
                        $config['limit'] ?? 10,
                        $config['offset'] ?? 0
                    );

                    break;

                default:
                    throw new \Exception("Facet type not supported : " . $config['type']);
            }
        }

        $result->setAggregations($aggregations);
        return $this;
    }

    private function aggregateByFacet(Query $query, string $field, int $limit, int $offset)
    {
        $queryBuilder = $this->connection
            ->createQueryBuilder()
            ->from($query->name);

        $filters = $query->filters;

        if (isset($filters[$field])) {
            unset($filters[$field]);
        }

        $this->applyFilters($queryBuilder, $query, $filters);
        return $this->aggregateByTerms($query, $queryBuilder, $field, $limit, $offset);
    }

    private function aggregateByTerms(Query $query, QueryBuilder $builder, string $field, int $limit, int $offset)
    {
        $builder->resetQueryPart('orderBy');
        $builder->resetQueryPart('orderBy');
        $builder->setFirstResult(null);
        $builder->setMaxResults(null);

        $builder->select([ $field . ' as name' , 'COUNT(*) as count' ]);
        $builder->groupBy($field);

        $sql = $builder->getSQL();

        foreach ($builder->getParameters() as $key => $value) {
            if (is_array($value)) {
                $value = array_map(function($value) {
                    if (is_int($value) || strtolower($value) === 'null') {
                        return $value;
                    }

                    return '"' . str_replace('"', '\"', $value) . '"';
                }, $value);

                $value = implode(', ', $value);
            } else {
                $value = '"' . $value . '"';
            }

            $sql = str_replace(':' . $key, $value, $sql);
        }

        $groupQuery = "SELECT name, count FROM (%s) AS T ORDER BY count DESC LIMIT %d, %d";
        $groupQuery = sprintf($groupQuery, $sql, $offset, $limit);

        $statement = $this->connection->query($groupQuery);

        if ($statement === false) {
            throw new \Exception("Aggregation failed");
        }

        $type = $query->schema->getFieldType($field);

        return array_map(function($item) use ($type, $field) {
            return [
                'name' => Field::formatValue($type, $field, $item['name']),
                'count' => (int) $item['count']
            ];
        }, $statement->fetchAll());
    }

    private function aggByFunction(QueryBuilder $queryBuilder, string $field, string $functionName)
    {
        $builder = clone $queryBuilder;
        $builder->select($functionName . '(' . $this->escapeFieldName($field) . ') as value');
        $builder->setFirstResult(0);
        $builder->setMaxResults(1);
        $value = $builder->execute()->fetch();

        return isset($value['value'])
            ? $value['value']
            : null;
    }
}
