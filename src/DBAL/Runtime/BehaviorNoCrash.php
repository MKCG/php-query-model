<?php

namespace MKCG\Model\DBAL\Runtime;

use MKCG\Model\Model;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\Drivers\DriverInterface;

class BehaviorNoCrash implements BehaviorInterface
{
    public function noDriver(Model $model) : Result
    {
        return new Result([], '');
    }

    public function unknownDriver(Model $model, string $driverName) : Result
    {
        return new Result([], '');
    }

    public function search(Model $model, Query $query, DriverInterface $driver) : Result
    {
        try {
            return $driver->search($query);
        } catch (\Exception $e) {
            return new Result([], '');
        }
    }
}
