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
        $options = $request->timeout > 0
            ? [ 'timeout' => $request->timeout ] + $request->options
            : $request->options;

        $client = $this->client ?: new Client();
        $guzzleRequest = $client->createRequest(
            $request->method,
            $request->url . $request->$uri,
            $request->headers,
            $request->body,
            $options
        );

        $response = $client->send($guzzleRequest);

        return new HttpResponse(
            $response->getStatusCode(),
            $response->getContentType(),
            (string) $response->getBody(),
            $request
        );
    }
}
