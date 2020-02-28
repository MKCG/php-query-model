<?php

namespace MKCG\Model\DBAL\Drivers\Adapters;

use MKCG\Model\DBAL\HttpRequest;
use MKCG\Model\DBAL\HttpResponse;

interface HttpClientInterface
{
    public function sendRequest(HttpRequest $request) : HttpResponse;
}
