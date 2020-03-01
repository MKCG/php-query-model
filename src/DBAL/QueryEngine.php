<?php

namespace MKCG\Model\DBAL;

use MKCG\Model\DBAL\Drivers\DriverInterface;
use MKCG\Model\Model;
use MKCG\Model\GenericSchema;
use MKCG\Model\DBAL\Runtime\BehaviorNoCrash;
use MKCG\Model\DBAL\Runtime\BehaviorInterface;


class QueryEngine
{
    private $drivers = [];
    private $schemaCache = [];
    private $defaultDriverName;
    private $injectAsCollectionByDefault;
    private $behavior;
    private $resultBuilder;

    private $optionDefaultValues = [
        'case_sensitive' => true,
        'max_query_time' => 5000,
        'allow_partial' => false,
    ];

    public function __construct(
        string $defaultDriverName = '',
        bool $injectAsCollectionByDefault = true,
        BehaviorInterface $behavior = null,
        ResultBuilderInterface $resultBuilder = null
    ) {
        $this->defaultDriverName = $defaultDriverName;
        $this->injectAsCollectionByDefault = $injectAsCollectionByDefault;
        $this->resultBuilder = $resultBuilder ?? new ResultBuilder();
        $this->behavior = $behavior ?? new BehaviorNoCrash($this->resultBuilder);
    }

    public function registerDriver(DriverInterface $driver, string $name, bool $isDefault = false)
    {
        $this->drivers[$name] = $driver;

        if ($isDefault) {
            $this->defaultDriverName = $name;
        }

        return $this;
    }

    public function scroll(Model $model, QueryCriteria $criteria, int $scrollBatchSize = 10) : \Generator
    {
        $alias = $model->getAlias();
        $criteria = $criteria->toArray();

        if (!isset($criteria[$alias])) {
            $criteria[$alias] = [];
        }

        if (!isset($criteria[$alias]['offset'])) {
            $criteria[$alias]['offset'] = 0;
        }

        if ($scrollBatchSize <= 0) {
            throw new \LogicException("Scroll batch size must be > 0 to be able to scroll");
        }

        $limit = $criteria[$alias]['limit'] ?? null;
        $criteria[$alias]['limit'] = $scrollBatchSize;

        $criteria[$alias]['scroll'] = new ScrollContext();
        $processed = 0;

        while (true) {
            if ($limit !== null) {
                if ($limit <= 0) {
                    break;
                } else if ($limit < $scrollBatchSize) {
                    $criteria[$alias]['limit'] = $limit;
                }
            }

            $result = $this->doQuery($model, $criteria);

            foreach ($result->getContent() as $item) {
                $processed++;
                yield $item;
            }

            if ($criteria[$alias]['scroll']->canScroll() === false || $processed === 0) {
                break;
            }

            $criteria[$alias]['offset'] += $scrollBatchSize;

            if ($limit !== null) {
                $limit -= $scrollBatchSize;
            }
        }
    }

    public function query(Model $model, QueryCriteria $criteria) : Result
    {
        return $this->doQuery($model, $criteria->toArray());
    }

    private function doQuery(Model $model, array $criteria, bool $includeSubModels = true) : Result
    {
        $result = null;

        if (empty($this->drivers)) {
            $result = $this->behavior->noDriver($model);
        } else {
            $schemaClassName = $model->getFromClass();

            if (!isset($this->schemaCache[$schemaClassName])) {
                $this->schemaCache[$schemaClassName] = (new $schemaClassName())
                    ->initConfigurations()
                    ->initRelations();
            }

            $schema = $this->schemaCache[$schemaClassName];
            $driverName = $schema->getDriverName() ?: $this->defaultDriverName;

            if (!isset($this->drivers[$driverName])) {
                $result = $this->behavior->unknownDriver($model, $driverName);
            } else {
                $alias = $model->getAlias();
                $options = isset($criteria[$alias]['options'])
                    ? $criteria[$alias]['options']
                    : [];

                $options = array_intersect_key(
                    $options + $this->optionDefaultValues,
                    array_fill_keys($this->drivers[$driverName]->getSupportedOptions(), null)
                );

                $query = Query::make($model, $schema, ['options' => $options] + ($criteria[$alias] ?: []));
                $result = $this->behavior->search($model, $query, $this->drivers[$driverName]);
            }
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

        if ($includeSubModels && !empty($result->getContent())) {
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
            $relation = $this->selectRelation($subModel, $relations);

            if (empty($relation)) {
                continue;
            }

            $filter = $this->makeRelationFilter($result, $relation);

            if ($filter === null) {
                // @tbd : Inject default value instead ?
                continue;
            }

            $alias = $subModel->getAlias();
            $firstKey = key($filter);

            if ($firstKey !== 0) {
                $filterIn = [ FilterInterface::FILTER_IN => $filter[$firstKey] ];

                $batchFilterIn = empty($criteria[$alias]['options']['multiple_requests'])
                    ? [ [ FilterInterface::FILTER_IN => $filter[$firstKey] ] ]
                    : array_map(function($value) {
                        return [ FilterInterface::FILTER_IN => $value ];
                    }, $filter[$firstKey]);

                $batchCount = count($batchFilterIn);
                $innerResults = [];

                foreach ($batchFilterIn as $i => $filterIn) {
                    $subCriteria = $criteria;

                    !isset($subCriteria[$alias]) && $subCriteria[$alias] = [];

                    $subCriteria[$alias]['filters'] = $this->mergeFiltersIn(
                        $subCriteria[$alias]['filters'] ?? [],
                        $firstKey,
                        $filterIn
                    );

                    if ($subCriteria['alias'][$filters][$firstKey][FilterInterface::FILTER_IN] === []) {
                        continue;
                    }

                    $subCriteria[$alias]['sub_context_ref'] = $firstKey;

                    if ($batchCount === 1 || true) {
                        $innerResult = $this->doQuery($subModel, $subCriteria);
                        $this->injectIncluded($alias, $result, $innerResult, $relation);
                    } else {
                        $innerResult = $this->doQuery($subModel, $subCriteria, false);
                        $innerResults = array_merge($innerResults, $innerResult);
                    }
                }

                /*
                if ($innerResults !== []) {
                    // @todo : make function to merge results
                    $innerResult = Result::merge($innerResults);
                    // @todo : change $criteria
                    $this->includeSubModels($innerResult, $model, $schema, $criteria);
                    $this->injectIncluded($alias, $result, $innerResult, $relation);
                }
                */
            } else {
                // @todo : Supports relations using many fields
                continue;
            }
        }

    }

    private function mergeFiltersIn(array $filters, string $field, $filterIn)
    {
        if (!isset($filters[$field])) {
            $filters[$field] = $filterIn;
        } else {
            $filters[$field] = $this->intersectFiltersIn($filterIn, $filters[$field]);
            $filters[$field][FilterInterface::FILTER_IN] = array_values($filters[$field][FilterInterface::FILTER_IN]);
        }

        return $filters;
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
        $injectAsCollection = isset($relation['isCollection'])
            ? filter_var($relation['isCollection'], FILTER_VALIDATE_BOOLEAN)
            : $this->injectAsCollectionByDefault;

        if (isset($relation['match']) && count($relation['match']) === 1) {
            $from = key($relation['match']);
            $to = current($relation['match']);
            $this->injectIncludedByRuleFromTo($alias, $parents, $children, $from, $to, $injectAsCollection);
        } else if (isset($relation['resolve_callback'])) {
            call_user_func($relation['resolve_callback'], $parents, $children, $injectAsCollection, $alias);
        }
    }

    private function injectIncludedByRuleFromTo(
        string $alias,
        Result $parents,
        Result $children,
        string $from,
        string $to,
        bool $isCollection
    ) {
        $mapFromParent = [];

        foreach ($parents->getContent() as $parentItem) {
            if (!isset($mapFromParent[$parentItem[$from]])) {
                $mapFromParent[$parentItem[$from]] = [];
            }

            $mapFromParent[$parentItem[$from]][] = $parentItem;
        }

        $mapItemByParent = [];

        foreach ($children->getContent() as $childItem) {
            if (!isset($mapItemByParent[$childItem[$to]])) {
                $mapItemByParent[$childItem[$to]] = [];
            }

            $mapItemByParent[$childItem[$to]][] = $childItem;
        }

        foreach ($mapItemByParent as $parentKey => $toInject) {
            if (isset($mapFromParent[$parentKey])) {
                foreach ($mapFromParent[$parentKey] as $parentItem) {
                    $parentItem[$alias] = $isCollection
                        ? $toInject
                        : $toInject[0];
                }
            }
        }
    }

    private function makeRelationFilter(Result $result, array $relation)
    {
        if (!empty($relation['match'])) {
            return $this->makeRelationFilterByRuleFromTo($result, $relation['match']);
        } else if (!empty($relation['match_callback'])) {
            return call_user_func($relation['match_callback'], $result);
        }

        return null;
    }

    private function makeRelationFilterByRuleFromTo(Result $result, array $matchRules)
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

    private function selectRelation(Model $model, array $relations) : array
    {
        $schemaClassName = $model->getFromClass();

        $matches = array_filter($relations, function($relation) use ($schemaClassName) {
            return ($relation['schema'] ?? '') === $schemaClassName;
        });

        $count = count($matches);

        if ($count === 0) {
            return [];
        }

        $relation = [];
        $alias = $model->getAlias();

        if ($count > 1) {
            if (isset($matches[$alias])) {
                return $relation = $matches[$alias];
            } else {
                return [];
            }
        }

        if ($alias === '') {
            $alias = key($matches);
            $model->setAlias($alias);
        }

        return array_pop($matches);
    }
}
