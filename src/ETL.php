<?php

namespace MKCG\Model;

final class ETL
{
    private $content;
    private $transformers = [];
    private $loaders = [];
    private $size;
    private $timeout;

    public static function extract(iterable $content, int $size = 100, int $timeout = 1000) : self
    {
        $etl = new static();

        $etl->content = $content;
        $etl->size = $size;
        $etl->timeout = $timeout;

        return $etl;
    }

    public function transform(callable $callable) : self
    {
        $this->transformers[] = $callable;
        return $this;
    }

    public function load(callable $callable) : self
    {
        $this->loaders[] = $callable;
        return $this;
    }

    public function run() : int
    {
        $lastTime = microtime(true);
        $bulk = [];
        $done = 0;

        foreach ($this->content as $item) {
            $bulk[] = $item;

            $trigger = isset($bulk[$this->size - 1])
                || microtime(true) - $lastTime > $this->timeout;

            if (!$trigger) {
                continue;
            }

            $this->push($this->applyTransformations($bulk));

            $done += count($bulk);
            $lastTime = microtime(true);
            $bulk = [];
        }

        if ($bulk !== []) {
            $this->push($this->applyTransformations($bulk));
            $done += count($bulk);
        }

        return $done;
    }

    private function applyTransformations(iterable $bulk)
    {
        foreach ($this->transformers as $transformer) {
            $bulk = array_map($transformer, $bulk);
        }

        return $bulk ?: [];
    }

    private function push(iterable $bulk)
    {
        foreach ($this->loaders as $loader) {
            $loader($bulk);
        }
    }
}
