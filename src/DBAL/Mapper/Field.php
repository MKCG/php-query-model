<?php

namespace MKCG\Model\DBAL\Mapper;

use MKCG\Model\FieldInterface;
use MKCG\Model\SchemaInterface;

class Field
{
    public static function formatValue(string $type, string $field, $value)
    {
        switch ($type) {
            case FieldInterface::TYPE_BOOL:
                return (bool) filter_var($value, FILTER_VALIDATE_BOOLEAN);

            case FieldInterface::TYPE_FLOAT:
                return (float) filter_var($value, FILTER_VALIDATE_FLOAT);

            case FieldInterface::TYPE_INT:
                return (int) filter_var($value, FILTER_VALIDATE_INT);

            default:
                return $value;
        }
    }
}
