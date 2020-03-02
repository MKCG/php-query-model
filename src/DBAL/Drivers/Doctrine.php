<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\FieldInterface;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\ResultBuilderInterface;

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
            ->applyFilters($queryBuilder, $query);

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

    private function applyFilters(QueryBuilder $queryBuilder, Query $query)
    {
        foreach ($query->filters as $field => $value) {
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
}
