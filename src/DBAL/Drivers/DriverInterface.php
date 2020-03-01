<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;

interface DriverInterface
{
    public function search(Query $query, ResultBuilderInterface $resultBuilder) : Result;
    public function getSupportedOptions() : array;
}
