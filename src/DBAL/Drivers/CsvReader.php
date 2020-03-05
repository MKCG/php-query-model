<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\AggregationInterface;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Filters\ContentFilter;
use MKCG\Model\DBAL\Mapper\Field;

class CsvReader implements DriverInterface
{
    private $path;

    public function __construct(string $path = '')
    {
        $this->path = $path;
    }

    public function getSupportedOptions() : array
    {
        return ['filepath', 'case_sensitive'];
    }

    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result
    {
        $handler = null;
        $header = [];

        if ($query->scroll !== null) {
            list($handler, $header) = $this->getScrollParams($query);
        } else {
            list($handler, $header) = $this->getHandlerWithHeader($query);
        }

        if (!is_resource($handler) || !is_array($header)) {
            throw new \Exception("Unable to process CSV file");
        }

        $results = [];
        $aggregations = [];
        $found = 0;

        if ($query->scroll !== null) {
            list($results, $found, $aggregations) = $this->scrollResults($query, $handler, $header);
        } else {
            list($results, $found, $aggregations) = $this->listResults($query, $handler, $header);
        }

        if (!empty($query->fields)) {
            $fields = array_fill_keys($query->fields, null);
            $results = array_map(function($item) use ($fields) {
                return array_intersect_key($item, $fields);
            }, $results);
        }

        $items = $resultBuilder
            ->build($results, $query)
            ->setCount($found);

        if (!empty($query->aggregations)) {
            $items->setAggregations($aggregations);
        }

        return $items;
    }

    private function listResults(Query $query, $handler, array $header) : array
    {
        $results = [];
        $found = 0;
        $agg = [];

        $avgAggs = [];

        while (true) {
            $line = fgetcsv($handler);

            if ($line === false || $line === null) {
                break;
            }

            $line = array_combine($header, $line);

            if (ContentFilter::matchQuery($line, $query)) {
                $found++;

                if (!empty($query->aggregations)) {
                    foreach ($query->aggregations as $config) {
                        if (!isset($line[$config['field']])) {
                            continue;
                        }

                        switch ($config['type']) {
                            case AggregationInterface::AVERAGE:
                                if (!isset($avgAggs[$config['field']])) {
                                    $avgAggs[$config['field']] = [ 'sum' => 0, 'count' => 0 ];
                                }

                                $avgAggs[$config['field']]['sum'] += $line[$config['field']];
                                $avgAggs[$config['field']]['count']++;

                                break;

                            case AggregationInterface::MIN:
                                if (!isset($agg['min'])) {
                                    $agg['min'] = [];
                                }

                                if (!isset($agg['min'][$config['field']])
                                    || $agg['min'][$config['field']] > $line[$config['field']]) {
                                    $agg['min'][$config['field']] = $line[$config['field']];
                                }

                                break;

                            case AggregationInterface::MAX:
                                if (!isset($agg['max'])) {
                                    $agg['max'] = [];
                                }

                                if (!isset($agg['max'][$config['field']])
                                    || $agg['max'][$config['field']] < $line[$config['field']]) {
                                    $agg['max'][$config['field']] = $line[$config['field']];
                                }

                                break;

                            case AggregationInterface::TERMS:
                            case AggregationInterface::FACET:
                            case AggregationInterface::QUANTILE:
                            default:
                                throw new \Exception("Facet type not supported : " . $config['type']);
                        }
                    }
                }

                if ($found <= $query->offset) {
                    continue;
                }

                if ($query->limit === 0 || $found <= $query->offset + $query->limit) {
                    $results[] = $line;
                }
            }
        }

        fclose($handler);

        if (!empty($avgAggs)) {
            $agg['averages'] = [];

            foreach ($avgAggs as $field => $value) {
                $agg['averages'][$field] = $value['sum'] / $value['count'];
            }
        }

        foreach ([ 'min' , 'max' ] as $aggType) {
            if (isset($agg[$aggType])) {
                foreach ($agg[$aggType] as $field => $value) {
                    $type = $query->schema->getFieldType($field);
                    $agg[$aggType][$field] = Field::formatValue($type, $field, $value);
                }
            }
        }

        return [ $results, $found , $agg ];
    }

    private function scrollResults(Query $query, $handler, array $header) : array
    {
        $found = $query->scroll->data['found'] ?? 0;
        $results = [];

        while ($found < $query->limit + $query->offset) {
            $line = fgetcsv($handler);

            if ($line === false || $line === null) {
                $query->scroll->stop();
                fclose($handler);
                break;
            }

            $line = array_combine($header, $line);

            if (ContentFilter::matchQuery($line, $query)) {
                $found++;

                if ($found > $query->offset) {
                    $results[] = $line;
                }
            }
        }

        $query->scroll->data['found'] = $found;

        return [ $results , $found , [] ];
    }

    private function getScrollParams(Query $query) : array
    {
        if (empty($query->scroll->data)) {
            $query->scroll->data = $this->getHandlerWithHeader($query);
        }

        return $query->scroll->data;
    }

    private function getHandlerWithHeader(Query $query) : array
    {
        $handler = $this->openFile($query);
        $header = null;

        if ($handler !== null) {
            $header = fgetcsv($handler);

            if (!is_array($header)) {
                fclose($handler);
                $header = null;
            }
        }

        return [$handler, $header];
    }

    protected function openFile(Query $query)
    {
        if (empty($query->context['options']['filepath']) && $this->path === '') {
            throw new \Exception("CSV filepath is undefined");
        }

        $filepath = $query->context['options']['filepath'];
        $isAbsolute = strpos($filepath, DIRECTORY_SEPARATOR) === 0;

        if (!$isAbsolute && $this->path !== '' && is_dir($this->path)) {
            $filepath = $this->path . DIRECTORY_SEPARATOR . $filepath;
        }

        if (!is_file($filepath)) {
            throw new \Exception("Invalid filepath : " . $filepath);
        }

        $handler = fopen($filepath, 'r');

        if ($handler === false || $handler === null) {
            throw new \Exception("Unable to open : " . $filepath);
        }

        return $handler;
    }
}
