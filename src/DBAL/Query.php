<?php

namespace MKCG\Model\DBAL;

use MKCG\Model\Model;
use MKCG\Model\SchemaInterface;

class Query
{
    public $name = '';
    public $filters = [];
    public $callableFilters = [];
    public $aggregations = [];
    public $sort = [];
    public $offset = 0;
    public $limit = 0;
    public $limitByParent = 0;
    public $fields = [];
    public $primaryKeys = [];
    public $entityClass = '';
    public $countResults = false;
    public $context = [];
    public $scroll = null;
    public $schema;

    public static function make(Model $model, SchemaInterface $schema, array $criteria) : self
    {
        $query = new static();

        $query->fields = $schema->getFields($model->getSetType());

        $query->name = isset($criteria['options']['name'])
            ? $criteria['options']['name']
            : $schema->getFullyQualifiedName();

        $query->primaryKeys = $schema->getPrimaryKeys();
        $query->entityClass = $schema->getEntityClass();
        $query->schema = $schema;

        if (isset($criteria['filters'])) {
            $query->filters = $criteria['filters'];
        }

        if (isset($criteria['aggregations'])) {
            $query->aggregations = $criteria['aggregations'];
        }

        if (isset($criteria['callable_filters'])) {
            $query->callableFilters = $criteria['callable_filters'];
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

        $query->context = $schema->getConfigurations();

        if (isset($criteria['sub_context_ref'])) {
            $query->context['parent_ref'] = $criteria['sub_context_ref'];
        }

        if (isset($criteria['scroll'])) {
            $query->scroll = $criteria['scroll'];
        }

        if (isset($criteria['options'])) {
            $query->context['options'] = $criteria['options'];
        }

        return $query;
    }
}
