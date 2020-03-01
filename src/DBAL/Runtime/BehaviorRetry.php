<?php

namespace MKCG\Model\DBAL\Runtime;

use MKCG\Model\Model;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\ResultBuilderInterface;
use MKCG\Model\DBAL\Drivers\DriverInterface;

class BehaviorRetry implements BehaviorInterface
{
    private $retry;

    public function __construct(int $retry = 1)
    {
        $this->retry = $retry;
    }

    public function noDriver(Model $model) : Result
    {
        throw new \Exception("No Driver defined");
    }

    public function unknownDriver(Model $model, string $driverName) : Result
    {
        throw new \Exception("Unknown Driver : " . $driverName);
    }

    public function search(Model $model, Query $query, DriverInterface $driver, ResultBuilderInterface $resultBuilder) : Result
    {
        $lastException;

        for ($i = 0; $i < $this->retry; $i++) {
            try {
                return $driver->search($query, $resultBuilder);
            } catch (\Exception $e) {
                $lastException = $e;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        } else {
            throw new \Exception("Search failed");
        }
    }
}
