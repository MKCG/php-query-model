<?php

namespace MKCG\Model\DBAL;

use MKCG\Model\DBAL\Drivers\DriverInterface;
use MKCG\Model\Model;
use MKCG\Model\GenericSchema;

class QueryEngine
{
    private $drivers = [];
    private $schemaCache = [];
    private $defaultDriverName;
    private $injectAsCollectionByDefault;

    public function __construct(
        string $defaultDriverName = '',
        bool $injectAsCollectionByDefault = true
    ) {
        $this->defaultDriverName = $defaultDriverName;
        $this->injectAsCollectionByDefault = $injectAsCollectionByDefault;
    }

    public function registerDriver(DriverInterface $driver, string $name, bool $isDefault = false)
    {
        $this->drivers[$name] = $driver;

        if ($isDefault) {
            $this->defaultDriverName = $name;
        }

        return $this;
    }

    public function scroll(Model $model, QueryCriteria $criteria, int $defaultLimit = 10) : \Generator
    {
        $alias = $model->getAlias();
        $criteria = $criteria->toArray();

        if (!isset($criteria[$alias])) {
            $criteria[$alias] = [];
        }

        if (!isset($criteria[$alias]['offset'])) {
            $criteria[$alias]['offset'] = 0;
        }

        if (!isset($criteria[$alias]['limit'])) {
            $criteria[$alias]['limit'] = $defaultLimit;
        }

        if ($criteria[$alias]['limit'] <= 0) {
            throw new \LogicException("Limit must be > 0 to be able to scroll");
        }

        while (true) {
            $result = $this->doQuery($model, $criteria);

            if ($result->getCount() < $criteria[$alias]['offset']) {
                break;
            }

            foreach ($result->getContent() as $item) {
                yield $item;
            }

            $criteria[$alias]['offset'] += $criteria[$alias]['limit'];
        }
    }

    public function query(Model $model, QueryCriteria $criteria) : Result
    {
        return $this->doQuery($model, $criteria->toArray());
    }

    private function doQuery(Model $model, array $criteria) : Result
    {
        if (empty($this->drivers)) {
            return Result::make([], '');
        }

        $schemaClassName = $model->getFromClass();

        if (!isset($this->schemaCache[$schemaClassName])) {
            $this->schemaCache[$schemaClassName] = new $schemaClassName();
        }

        $schema = $this->schemaCache[$schemaClassName];
        $driverName = $schema->getSourceType() ?: $this->defaultDriverName;

        if (!isset($this->drivers[$driverName])) {
            return Result::make([], '');
        }

        $alias = $model->getAlias();
        $query = $this->buildQuery($model, $schema, $criteria[$alias] ?: []);

        try {
            $result = $this->drivers[$driverName]->search($query);
        } catch (\Exception $e) {
            return Result::make([], '');
        }

        $pkFields = $schema->getPrimaryKeys();

        if (!empty($pkFields)) {
            sort($pkFields);

            /*
            $result->setIncludedIdsFormatter(function($content) use ($pkFields) {
                $included = [];

                foreach ($content as $item) {
                    $values = array_map(function($field) use ($item) {
                        return $field . ':' . $item[$field] ?? '';
                    }, $pkFields);


                    $included[] = implode('|', $values);
                }

                return $included;
            });
            //*/
        }

        // var_dump($result->getIncludedIds());
        // die;

        if (!empty($result->getContent())) {
            $this->includeSubModels($result, $model, $schema, $criteria);
        }

        return $result;
    }

    private function includeSubModels(Result $result, Model $model, GenericSchema $schema, array $criteria)
    {
        $included = $model->getWith();

        if (empty($included)) {
            return $this;
        }

        $relations = $schema->getRelations();

        foreach ($included as $subModel) {
            $subSchemaClass = $subModel->getFromClass();
            $matches = array_filter($relations, function($relation) use ($subSchemaClass) {
                return ($relation['schema'] ?? '') === $subSchemaClass;
            });

            $count = count($matches);
            $relation = [];
            $alias = $subModel->getAlias();

            if ($count === 0) {
                // @tbd : Inject default value instead ?
                continue;
            } else if ($count > 1) {
                if (isset($matches[$alias])) {
                    $relation = $matches[$alias];
                } else {
                    // @tbd : Inject default value instead ?
                    continue;
                }
            } else {
                if ($alias === '') {
                    $alias = key($matches);
                    $subModel->setAlias($alias);
                }

                $relation = array_pop($matches);
            }

            if (empty($relation['match'])) {
                // @tbd : Inject default value instead ?
                continue;
            }

            $subCriteria = $criteria;
            $filter = $this->makeRelationFilter($result, $relation['match']);
            $firstKey = key($filter);

            if ($firstKey !== 0) {
                $filterIn = [ FilterInterface::FILTER_IN => $filter[$firstKey] ];

                if (!isset($subCriteria[$alias]['filters'][$firstKey])) {
                    $subCriteria[$alias]['filters'][$firstKey] = $filterIn;
                } else {
                    $subCriteria[$alias]['filters'][$firstKey] = $this->intersectFiltersIn(
                        $filterIn,
                        $subCriteria[$alias]['filters'][$firstKey]
                    );

                    $subCriteria[$alias]['filters'][$firstKey][FilterInterface::FILTER_IN] = array_values(
                        $subCriteria[$alias]['filters'][$firstKey][FilterInterface::FILTER_IN]
                    );

                    if ($subCriteria[$alias]['filters'][$firstKey][FilterInterface::FILTER_IN] === []) {
                        continue;
                    }
                }

                $subCriteria[$alias]['sub_context_ref'] = $firstKey;
            } else {
                // @todo : Supports relations using many fields
                continue;
            }

            $innerResult = $this->doQuery($subModel, $subCriteria);
            $this->injectIncluded($alias, $result, $innerResult, $relation);
        }

    }

    private function intersectFiltersIn(array $filters, $toPush)
    {
        if (!is_array($toPush)) {
            if (!in_array($toPush, $filters[FilterInterface::FILTER_IN])) {
                $filters[FilterInterface::FILTER_IN] = [];
            }

            return $filters;
        }

        $isList = array_reduce(
            array_keys($toPush),
            function($acc, $val) { return $acc && is_numeric($val); },
            true
        );

        if ($isList) {
            $filters[FilterInterface::FILTER_IN] = array_intersect(
                $filters[FilterInterface::FILTER_IN],
                $toPush
            );

            return $filters;
        }

        foreach ($toPush as $filterType => $values) {
            if (strtolower($filterType) !== FilterInterface::FILTER_IN) {
                $filters[$filterType] = $values;
            } else if (!is_array($values) && !in_array($values, $filters[FilterInterface::FILTER_IN])) {
                $filters[FilterInterface::FILTER_IN] = [];
            } else if (is_array($values)) {
                $filters[FilterInterface::FILTER_IN] = array_intersect(
                    $filters[FilterInterface::FILTER_IN],
                    $values
                );
            }
        }

        return $filters;
    }

    private function injectIncluded(string $alias, Result $parents, Result $children, array $relation)
    {
        $matchRules = $relation['match'] ?: [];

        if (count($matchRules) === 1) {
            $from = key($matchRules);
            $to = current($matchRules);

            $mapFromParent = [];

            foreach ($parents->getContent() as $parentItem) {
                $mapFromParent[$parentItem[$from]] = $parentItem;
            }

            $mapItemByParent = [];

            foreach ($children->getContent() as $childItem) {
                if (!isset($mapItemByParent[$childItem[$to]])) {
                    $mapItemByParent[$childItem[$to]] = [];
                }

                $mapItemByParent[$childItem[$to]][] = $childItem;
            }

            $injectAsCollection = isset($relation['isCollection'])
                ? filter_var($relation['isCollection'], FILTER_VALIDATE_BOOLEAN)
                : $this->injectAsCollectionByDefault;

            foreach ($mapItemByParent as $parentKey => $toInject) {
                if (isset($mapFromParent[$parentKey])) {
                    $mapFromParent[$parentKey][$alias] = $injectAsCollection
                        ? $toInject
                        : $toInject[0];
                }
            }
        } else {
            // @todo : Supports relations using many fields
        }
    }

    private function makeRelationFilter(Result $result, array $matchRules)
    {
        $filters = [];

        if (count($matchRules) === 1) {
            $filter = [];
            $from = key($matchRules);
            $to = current($matchRules);

            foreach ($result->getContent() as $item) {
                $filter[] = $item[$from];
            }

            $filters[$to] = array_unique($filter);
        } else {
            foreach ($result->getContent() as $item) {
                $filter = [];

                foreach ($matchRules as $from => $to) {
                    $filter[$to] = $item[$from];
                }

                $filters[] = $filter;
            }
        }

        return $filters;
    }

    private function buildQuery(Model $model, GenericSchema $schema, array $criteria) : Query
    {
        $query = new Query();

        $query->fields = $schema->getFields($model->getSetType());
        $query->table = $schema->getFullyQualifiedTableName();
        $query->primaryKeys = $schema->getPrimaryKeys();
        $query->entityClass = $schema->getEntityClass();

        if (isset($criteria['filters'])) {
            $query->filters = $criteria['filters'];
        }

        if (isset($criteria['limit'])) {
            $query->limit = $criteria['limit'];
        }

        if (isset($criteria['limit_by_parent'])) {
            $query->limitByParent = $criteria['limit_by_parent'];
        }

        if (isset($criteria['offset'])) {
            $query->offset = $criteria['offset'];
        }

        if (isset($criteria['sort'])) {
            $query->sort = $criteria['sort'];
        }

        if (isset($criteria['sub_context_ref'])) {
            $query->context['parent_ref'] = $criteria['sub_context_ref'];
        }

        return $query;
    }
}
