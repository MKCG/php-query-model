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
    private $relations = [];
    private $configurations = [];

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

    public function getConfigurations() : array
    {
        return $this->configurations;
    }

    public function initConfigurations() : self
    {
        return $this;
    }

    public function initRelations() : self
    {
        return $this;
    }

    protected function addConfiguration(string $name, $configuration) : self
    {
        $this->configurations[$name] = $configuration;
        return $this;
    }

    protected function addRelation(
        string $alias,
        string $schemaClass,
        string $from,
        string $to,
        bool $isCollection = true
    ) : self
    {
        $this->relations[$alias] = [
            'schema' => $schemaClass,
            'match' => [ $from => $to ],
            'isCollection' => $isCollection
        ];

        return $this;
    }

    protected function addRelationResolver(
        string $alias,
        string $schemaClass,
        callable $matcher,
        callable $resolver,
        bool $isCollection = true
    ) : self {
        $this->relations[$alias] = [
            'schema' => $schemaClass,
            'match_callback' => $matcher,
            'resolve_callback' => $resolver,
            'isCollection' => $isCollection
        ];

        return $this;
    }
}
