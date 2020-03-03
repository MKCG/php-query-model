<?php

namespace MKCG\Model;

abstract class GenericSchema implements SchemaInterface
{
    protected $driverName = '';
    protected $name = '';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = [];
    protected $filterableFields = [];
    protected $types = [];
    private $relations = [];
    private $configurations = [];
    private $definitions = [];

    public static function make(string $setType = '', string $alias = '') : Model
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

    public function getFieldType(string $name) : ?string
    {
        return isset($this->definitions[$name]['type'])
            ? $this->definitions[$name]['type']
            : null;
    }

    public function isFilterable(string $name) : bool
    {
        return !empty($this->definitions[$name]['filterable']);
    }

    public function isSortable(string $name) : bool
    {
        return !empty($this->definitions[$name]['sortable']);
    }

    public function isAggregatable(string $name) : bool
    {
        return !empty($this->definitions[$name]['aggregatable']);
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

    public function init() : self
    {
        $this->initConfigurations();
        $this->initRelations();
        $this->initFields();

        return $this;
    }

    protected function initFields()
    {
        return $this;
    }

    protected function initConfigurations()
    {
        return $this;
    }

    protected function initRelations()
    {
        return $this;
    }

    protected function setFieldDefinition(
        string $name,
        string $type,
        bool $filterable = false,
        bool $sortable = false,
        bool $aggregatable = false
    ) : self
    {
        $this->definitions[$name] = [
            'type' => $type,
            'filterable' => $filterable,
            'sortable' => $sortable,
            'aggregatable' => $aggregatable,
        ];

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
