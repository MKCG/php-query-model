<?php

namespace MKCG\Model\DBAL;

class ResultBuilder implements ResultBuilderInterface
{
    private static $transformers = [
        'csv'
    ];

    public function build(iterable $content, Query $query = null) : Result
    {
        $transformers = $query->schema->getTransformers();
        $transformers = array_filter($transformers, function($value) {
            return in_array($value, self::$transformers);
        });
        
        foreach ($content as $i => $row) {
            foreach ($row as $field => $value) {
                $content[$i][$field] = Mapper\Field::formatValue($query->schema->getFieldType($field), $field, $value);
            }
        }

        if ($transformers !== []) {
            foreach ($content as $i => $row) {
                foreach ($transformers as $field => $transformer) {
                    if (isset($row[$field]) && in_array($transformer, self::$transformers)) {
                        $content[$i][$field] = $this->applyTransformation($transformer, $field, $row[$field]);
                    }
                }
            }
        }

        return Result::make($content, $query !== null ? $query->entityClass : '');
    }

    private function applyTransformation(string $transformer, string $field, $value)
    {
        switch ($transformer) {
            case 'csv':
                return explode(',', $value);
            default:
                return $value;
        }
    }
}
