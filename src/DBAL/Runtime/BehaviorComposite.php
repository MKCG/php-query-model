<?php

namespace MKCG\Model\DBAL\Runtime;

use MKCG\Model\Model;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Drivers\DriverInterface;

class BehaviorComposite implements BehaviorInterface
{
    private $behaviorNoDriver;
    private $behaviorUnknownDriver;
    private $behaviorSearch;

    public function __construct(
        BehaviorInterface $behaviorNoDriver,
        BehaviorInterface $behaviorUnknownDriver,
        BehaviorInterface $behaviorSearch
    ) {
        $this->behaviorNoDriver = $behaviorNoDriver;
        $this->behaviorUnknownDriver = $behaviorUnknownDriver;
        $this->behaviorSearch = $behaviorSearch;
    }

    public function noDriver(Model $model) : Result
    {
        return $this->behaviorNoDriver->noDriver($model);
    }

    public function unknownDriver(Model $model, string $driverName) : Result
    {
        return $this->behavior->unknownDriver($model, $driverName);
    }

    public function search(Model $model, Query $query, DriverInterface $driver, ResultBuilderInterface $resultBuilder) : Result
    {
        return $this->behavior->search($model, $query, $driver, $resultBuilder);
    }
}
