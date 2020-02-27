<?php

namespace MKCG\Model;

abstract class GenericSchema
{
    protected $driverName = '';
    protected $name = '';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = [];
    protected $filterableFields = [];
    protected $types = [];
    protected $relations = [];

    public static function make(string $setType = '', string $alias = '')
    {
        return new Model(static::class, $setType, $alias);
    }

    public function getDriverName() : string
    {
        return $this->driverName;
    }

    public function getFullyQualifiedName()
    {
        return $this->name;
    }

    public function getEntityClass()
    {
        return $this->entityClass;
    }

    public function getPrimaryKeys() : array
    {
        return $this->primaryKeys;
    }

    public function getFilterableFields() : array
    {
        return $this->filterableFields;
    }

    public function getFields(string $type) : array
    {
        if (isset($this->types[$type])) {
            return $this->types[$type];
        }

        if (isset($this->types['public'])) {
            return $this->types['public'];
        }

        return isset($this->types['default'])
            ? $this->types['default']
            : [];
    }

    public function getRelation(string $name) : array
    {
        return $this->relations[$name] ?? [];
    }

    public function getRelations() : array
    {
        return $this->relations;
    }
}
