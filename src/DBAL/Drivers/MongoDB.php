<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;

class MongoDB implements DriverInterface
{
    public function search(Query $query) : Result
    {
        return Result::make([], '');
    }
}
