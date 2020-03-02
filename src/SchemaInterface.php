<?php

namespace MKCG\Model;

interface SchemaInterface
{
    public static function make(string $setType = '', string $alias = '') : Model;
    public function getDriverName() : string;
    public function getFullyQualifiedName();
    public function getEntityClass();
    public function getPrimaryKeys() : array;
    public function getFields(string $type) : array;
    public function getFieldType(string $name) : ?string;
    public function isFilterable(string $name) : bool;
    public function isSortable(string $name) : bool;
    public function isAggregatable(string $name) : bool;
    public function getRelation(string $name) : array;
    public function getRelations() : array;
    public function getConfigurations() : array;
    public function init() : self;
}
