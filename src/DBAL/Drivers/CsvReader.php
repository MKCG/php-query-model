<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;

class CsvReader implements DriverInterface
{
    private $path;

    public function __construct(string $path = '')
    {
        $this->path = $path === ''
            ? $path
            : $path . DIRECTORY_SEPARATOR;
    }

    public function search(Query $query) : Result
    {
        $handler = null;
        $header = [];

        if (!empty($query->context['scroll'])) {
            if (empty($query->context['scroll']->data['handler'])) {
                $query->context['scroll']->data['handler'] = $this->openFile($query->name);
            }

            $handler = $query->context['scroll']->data['handler'];

            if ($handler === null) {
                return Result::make([]);
            }

            if (empty($query->context['scroll']->data['header'])) {
                $header = fgetcsv($handler);

                if (!is_array($header)) {
                    fclose($handler);
                    $query->context['scroll']->data['end'] = true;

                    return Result::make([]);
                }

                $query->context['scroll']->data['header'] = $header;
            } else {
                $header = $query->context['scroll']->data['header'];
            }
        } else {
            $handler = $this->openFile($query->name);

            if ($handler === null) {
                return Result::make([]);
            }

            $header = fgetcsv($handler);

            if (!is_array($header)) {
                fclose($handler);
                return Result::make([]);
            }
        }

        $results = [];
        $found = 0;

        if (!empty($query->context['scroll'])) {
            while ($found < $query->limit) {
                $line = fgetcsv($handler);

                if ($line === false || $line === null) {
                    $query->context['scroll']->data['end'] = true;
                    fclose($handler);
                    break;
                }

                $line = array_combine($header, $line);

                if ($this->matchQuery($line, $query)) {
                    $results[] = $line;
                    $found++;
                }
            }
        } else {
            while (true) {
                $line = fgetcsv($handler);

                if ($line === false || $line === null) {
                    break;
                }

                $line = array_combine($header, $line);

                if ($this->matchQuery($line, $query)) {
                    if ($found >= $query->offset && $found < $query->offset + $query->limit) {
                        $results[] = $line;
                    }

                    $found++;
                }
            }

            fclose($handler);
        }

        if (!empty($query->fields)) {
            $fields = array_fill_keys($query->fields, null);
            $results = array_map(function($item) use ($fields) {
                return array_intersect_key($item, $fields);
            }, $results);
        }

        return Result::make($results, $query->entityClass);
    }

    protected function openFile(string $name)
    {
        $filepath = $this->path . $name;
        $handler = fopen($filepath, 'r');

        if ($handler === false || $handler === null) {
            throw new \Exception("Unable to open : " . $filepath);
        }

        return $handler;
    }

    private function matchQuery(array $line, Query $query) : bool
    {
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
                        if (mb_strpos($line[$field], $value) === false) {
                            return false;
                        }

                        break;

                    default:
                        throw new \LogicException("Filter not supported yet");
                        break;
                }
            }
        }

        return true;
    }
}
