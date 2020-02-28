<?php

namespace MKCG\Model\DBAL\Drivers\Adapters;

use MKCG\Model\DBAL\HttpRequest;
use MKCG\Model\DBAL\HttpResponse;
use Guzzle\Http\Client;

class Guzzle implements HttpClientInterface
{
    private $client;

    public function __construct(Client $client = null)
    {
        $this->client = $client;
    }

    public function sendRequest(HttpRequest $request) : HttpResponse
    {
        $client = $this->client ?: new Client();
        $request = $client->createRequest(
            $request->method,
            $request->url . $request->$uri,
            $request->headers,
            $request->body,
            $request->options
        );

        $response = $client->send($request);

        return new HttpResponse(
            $response->getStatusCode(),
            $response->getContentType(),
            (string) $response->getBody()
        );
    }
}
