<?php

namespace MKCG\Model\DBAL;

interface ResultBuilderInterface
{
    public function build(iterable $content, Query $query = null) : Result;
}
