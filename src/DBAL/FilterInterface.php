<?php

namespace MKCG\Model\DBAL;

interface FilterInterface
{
    const FILTER_OR = 'or';
    const FILTER_AND = 'and';
    const FILTER_IN = 'in';
    const FILTER_NOT_IN = 'not_in';
    const FILTER_GREATER_THAN = 'gt';
    const FILTER_GREATER_THAN_EQUAL = 'gte';
    const FILTER_LESS_THAN = 'lt';
    const FILTER_LESS_THAN_EQUAL = 'lte';
    const FILTER_FULLTEXT_MATCH = 'fulltext_match';
}
