<?php

namespace MKCG\Model\DBAL;

class ResultBuilder implements ResultBuilderInterface
{
    public function build(iterable $content, Query $query = null) : Result
    {
        return Result::make($content, $query !== null ? $query->entityClass : '');
    }
}
