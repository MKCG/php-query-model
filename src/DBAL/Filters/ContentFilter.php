<?php

namespace MKCG\Model\DBAL\Filters;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\FilterInterface;

class ContentFilter
{
    public static function matchQuery(array $line, Query $query) : bool
    {
        $isCaseSensitive = !empty($query->context['options']['case_sensitive']);

        foreach ($query->filters as $field => $filters) {
            foreach ($filters as $type => $value) {
                switch ($type) {
                    case FilterInterface::FILTER_IN:
                    case FilterInterface::FILTER_GREATER_THAN:
                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                    case FilterInterface::FILTER_LESS_THAN:
                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        if (!isset($line[$field])) {
                            return false;
                        }

                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        if (!isset($line[$field])) {
                            return true;
                        }

                        break;
                }

                if (!is_array($value)
                    && in_array($type, [FilterInterface::FILTER_IN, FilterInterface::FILTER_NOT_IN])) {
                    $value = [ $value ];
                }

                switch ($type) {
                    case FilterInterface::FILTER_IN:
                        if (!in_array($line[$field], $value)) {
                            return false;
                        }

                        break;

                    case FilterInterface::FILTER_NOT_IN:
                        if (in_array($line[$field], $value)) {
                            return false;
                        }

                        break;

                    case FilterInterface::FILTER_GREATER_THAN:
                    case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                    case FilterInterface::FILTER_LESS_THAN:
                    case FilterInterface::FILTER_LESS_THAN_EQUAL:
                        $numericValue = filter_var($line[$field], FILTER_VALIDATE_FLOAT);
                        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

                        if ($numericValue === false || $value === false) {
                            return false;
                        }

                        if ($numericValue !== false) {
                            switch ($type) {
                                case FilterInterface::FILTER_GREATER_THAN:
                                    if ($value <= $numericValue) {
                                        return false;
                                    }

                                    break;

                                case FilterInterface::FILTER_GREATER_THAN_EQUAL:
                                    if ($value < $numericValue) {
                                        return false;
                                    }

                                    break;

                                case FilterInterface::FILTER_LESS_THAN:
                                    if ($value >= $numericValue) {
                                        return false;
                                    }

                                    break;

                                case FilterInterface::FILTER_LESS_THAN_EQUAL:
                                    if ($value > $numericValue) {
                                        return false;
                                    }

                                    break;
                            }
                        }

                        break;

                    case FilterInterface::FILTER_FULLTEXT_MATCH:
                        if (!is_string($line[$field])) {
                            return false;
                        }

                        $text = $line[$field];

                        if (!$isCaseSensitive) {
                            $text = mb_strtolower($text);
                            $value = mb_strtolower($value);
                        }

                        if (mb_strpos($text, $value) === false) {
                            return false;
                        }

                        break;

                    default:
                        throw new \LogicException("Filter not supported yet");
                }
            }
        }

        foreach ($query->callableFilters as $callback) {
            if (call_user_func($callback, $query, $line) === false) {
                return false;
            }
        }

        return true;
    }
}
