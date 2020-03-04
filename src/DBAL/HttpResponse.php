<?php

namespace MKCG\Model\DBAL;

class HttpResponse
{
    public $statusCode;
    public $body;
    public $contentType;
    public $request;

    public function __construct(int $statusCode, string $contentType, string $body, HttpRequest $request)
    {
        $this->statusCode = $statusCode;
        $this->contentType = $contentType;
        $this->body = $body;
        $this->request = $request;
    }
}
