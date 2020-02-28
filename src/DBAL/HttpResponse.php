<?php

namespace MKCG\Model\DBAL;

class HttpResponse
{
    public $statusCode;
    public $body;

    public function __construct(int $statusCode, string $body)
    {
        $this->statusCode = $statusCode;
        $this->body = $body;
    }
}
