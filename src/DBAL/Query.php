<?php

namespace MKCG\Model\DBAL;

class Query
{
    public $name = '';
    public $filters = [];
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
}
