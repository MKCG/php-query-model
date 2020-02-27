<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;

interface DriverInterface
{
    public function search(Query $query) : Result;
}
