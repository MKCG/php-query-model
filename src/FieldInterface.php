<?php

namespace MKCG\Model;

interface FieldInterface
{
    const TYPE_BINARY   = 'binary';
    const TYPE_BOOL     = 'bool';
    const TYPE_DATETIME = 'datetime';
    const TYPE_ENUM     = 'enum';
    const TYPE_FLOAT    = 'float';
    const TYPE_INT      = 'int';
    const TYPE_STRING   = 'string';

    const NUMERIC_TYPES = [
        self::TYPE_INT,
        self::TYPE_FLOAT
    ];
}
