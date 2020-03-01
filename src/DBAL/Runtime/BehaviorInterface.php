<?php

namespace MKCG\Model\DBAL\Runtime;

use MKCG\Model\Model;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Drivers\DriverInterface;

interface BehaviorInterface
{
    public function noDriver(Model $model) : Result;
    public function unknownDriver(Model $model, string $driverName) : Result;
    public function search(Model $model, Query $query, DriverInterface $driver, ResultBuilderInterface $resultBuilder) : Result;
}
