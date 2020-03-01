<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\Filters\ContentFilter;

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

    public function search(Query $query) : Result
    {
        $handler = null;
        $header = [];

        if (!empty($query->context['scroll'])) {
            list($handler, $header) = $this->getScrollParams($query);
        } else {
            list($handler, $header) = $this->getHandlerWithHeader($query);
        }

        if (!is_resource($handler) || !is_array($header)) {
            throw new \Exception("Unable to process CSV file");
        }

        $results = [];
        $found = 0;

        if (!empty($query->context['scroll'])) {
            list($results, $found) = $this->scrollResults($query, $handler, $header);
        } else {
            list($results, $found) = $this->listResults($query, $handler, $header);
        }

        if (!empty($query->fields)) {
            $fields = array_fill_keys($query->fields, null);
            $results = array_map(function($item) use ($fields) {
                return array_intersect_key($item, $fields);
            }, $results);
        }

        return Result::make($results, $query->entityClass);
    }

    private function listResults(Query $query, $handler, array $header) : array
    {
        $results = [];
        $found = 0;

        while (true) {
            $line = fgetcsv($handler);

            if ($line === false || $line === null) {
                break;
            }

            $line = array_combine($header, $line);

            if (ContentFilter::matchQuery($line, $query)) {
                $found++;

                if ($found <= $query->offset) {
                    continue;
                }

                if ($query->limit === 0 || $found <= $query->offset + $query->limit) {
                    $results[] = $line;
                }
            }
        }

        fclose($handler);

        return [ $results, $found ];
    }

    private function scrollResults(Query $query, $handler, array $header) : array
    {
        $found = $query->context['scroll']->data['found'] ?? 0;
        $results = [];

        while ($found < $query->limit + $query->offset) {
            $line = fgetcsv($handler);

            if ($line === false || $line === null) {
                $query->context['scroll']->stop();
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

        $query->context['scroll']->data['found'] = $found;

        return [ $results , $fetched ];
    }

    private function getScrollParams(Query $query) : array
    {
        if (empty($query->context['scroll']->data)) {
            $query->context['scroll']->data = $this->getHandlerWithHeader($query);
        }

        return $query->context['scroll']->data;
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
