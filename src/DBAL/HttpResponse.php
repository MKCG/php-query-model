<?php

namespace MKCG\Model\DBAL;

class HttpResponse
{
    public $statusCode;
    public $body;
    public $contentType;

    public function __construct(int $statusCode, string $contentType, string $body)
    {
        $this->statusCode = $statusCode;
        $this->contentType = $contentType;
        $this->body = $body;
    }
}
