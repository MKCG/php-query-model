<?php

namespace MKCG\Model\DBAL\Runtime;

use MKCG\Model\Model;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Drivers\DriverInterface;

class BehaviorNoCrash implements BehaviorInterface
{
    private $resultBuilder;

    public function __construct(ResultBuilderInterface $resultBuilder)
    {
        $this->resultBuilder = $resultBuilder;
    }

    public function noDriver(Model $model) : Result
    {
        return $this->resultBuilder->build([]);
    }

    public function unknownDriver(Model $model, string $driverName) : Result
    {
        return $this->resultBuilder->build([]);
    }

    public function search(Model $model, Query $query, DriverInterface $driver, ResultBuilderInterface $resultBuilder) : Result
    {
        try {
            return $driver->search($query, $resultBuilder);
        } catch (\Exception $e) {
            return $this->resultBuilder->build([], $query);
        }
    }
}
