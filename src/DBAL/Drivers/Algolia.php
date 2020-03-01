<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;

class Algolia implements DriverInterface
{
    public function getSupportedOptions() : array
    {
        return [];
    }

    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result
    {
        return $resultBuilder->build([], $query);
    }
}
