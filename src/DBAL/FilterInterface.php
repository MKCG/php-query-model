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
    const FILTER_CUSTOM = 'custom';

    const SET_FILTERS = [
        self::FILTER_IN,
        self::FILTER_NOT_IN
    ];

    const RANGE_FILTERS = [
        self::FILTER_GREATER_THAN,
        self::FILTER_GREATER_THAN_EQUAL,
        self::FILTER_LESS_THAN,
        self::FILTER_LESS_THAN_EQUAL
    ];
}
