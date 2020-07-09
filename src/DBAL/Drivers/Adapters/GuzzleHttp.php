<?php

namespace MKCG\Model\DBAL\Drivers\Adapters;

use MKCG\Model\DBAL\HttpRequest;
use MKCG\Model\DBAL\HttpResponse;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class GuzzleHttp implements HttpClientInterface
{
    private $client;

    public function __construct(Client $client = null)
    {
        $this->client = $client;
    }

    public function sendRequest(HttpRequest $request) : HttpResponse
    {
        $options = $request->timeout > 0
            ? [ 'timeout' => $request->timeout ] + $request->options
            : $request->options;

        $guzzleRequest = new Request(
            $request->method,
            $request->url . $request->uri,
            $request->headers,
            $request->body
        );

        try {
            $response = ($this->client ?: new Client())->send($guzzleRequest, $options);
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            $response = $e->getResponse();
        }

        $contentType = $response->getHeader('Content-Type');
        $contentType = current($contentType) ?: '';

        return new HttpResponse(
            $response->getStatusCode(),
            $contentType,
            (string) $response->getBody(),
            $request
        );
    }
}
