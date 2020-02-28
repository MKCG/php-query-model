<?php

namespace MKCG\Model\DBAL\Mapper;

class Json
{
    public static function mapItem($json, array $fields)
    {
        if (!is_array($json)) {
            return [];
        }

        $map = [];

        foreach ($fields as $key => $value) {
            if (is_numeric($key)) {
                $map[$value] = $json[$value] ?? null;
            } else if (is_array($value) && isset($json[$key])) {
                $map[$key] = static::mapJson($json[$key], $value);
            } else {
                $map[$key] = null;
            }
        }

        return $map;
    }
}
