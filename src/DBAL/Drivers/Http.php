<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\Configurations;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\HttpRequest;
use MKCG\Model\DBAL\HttpResponse;
use MKCG\Model\DBAL\ScrollContext;
use MKCG\Model\DBAL\Drivers\Adapters\HttpClientInterface;

abstract class Http implements DriverInterface
{
    private $client;
    private $defaultUrl;
    private $defaultUri;
    private $defaultMethod;
    private $defaultHeaders;
    private $defaultBody;
    private $defaultOptions;

    public function __construct(
        HttpClientInterface $client,
        string $defaultUrl = '',
        string $defaultUri = '',
        string $defaultMethod = 'GET',
        array $defaultHeaders = [],
        $defaultBody = null,
        array $defaultOptions = []
    ) {
        $this->client = $client;
        $this->defaultUrl = $defaultUrl;
        $this->defaultUri = $defaultUri;
        $this->defaultMethod = $defaultMethod;
        $this->defaultHeaders = $defaultHeaders;
        $this->defaultBody = $defaultBody;
        $this->defaultOptions = $defaultOptions;
    }

    public function search(Query $query) : Result
    {
        $body = $this->makeRequestBody($query);
        $response = $this->getResponse($query, $body);
        $content = $response->statusCode >= 200 && $response->statusCode < 300
            ? $this->makeResultList($query, $response)
            : [];

        if (!empty($query->context['scroll'])) {
            $this->updateScrollContext($query->context['scroll']);
        }

        return Result::make($content, $query->entityClass);
    }

    protected function makeRequestBody(Query $query)
    {
        return null;
    }

    abstract protected function makeResultList(Query $query, HttpResponse $response) : array;

    protected function updateScrollContext(ScrollContext $scroll)
    {
        $scroll->data['end'] = true;
    }

    private function getResponse(Query $query, $body = null)
    {
        if (empty($query->context['http']) || !is_a($query->context['http'], Configurations\Http::class)) {
            throw new \Exception("Invalid HTTP configuration provided");
        }

        $configuration = $query->context['http'];

        $request = new HttpRequest();
        $request->url = $configuration->getUrl() ?: $this->defaultUrl;
        $request->uri = $configuration->getUri() ?: $this->defaultUri;
        $request->method = $configuration->getMethod() ?: $this->defaultMethod;
        $request->headers = $configuration->getHeaders() ?: $this->defaultHeaders;
        $request->options = $configuration->getOptions() ?: $this->defaultOptions;

        $request->url = filter_var($request->url, FILTER_VALIDATE_URL);

        if (empty($request->url)) {
            throw new \Exception("Empty URL provided");
        }

        return $this->client->sendRequest($request);
    }

    protected function mapSimpleXMLElement(\SimpleXMLElement $node, array $fields) : ?array
    {
        $item = [];

        foreach ($fields as $key => $value) {
            if (is_numeric($key)) {
                $element = $node->{$value};
                $item[$value] = $element[0]
                    ? (string) $element[0]
                    : null;
            } else if (is_array($value)) {
                $subitems = [];

                foreach ($node->{$key} as $element) {
                    $subitems[] = $this->mapSimpleXMLElement($element, $value);
                }

                $item[$key] = $subitems;
            }
        }

        return $item;
    }

    protected function mapDOMElement(\DOMElement $element, array $fields)
    {
        $item = [];

        foreach ($fields as $key => $value) {
            if (is_numeric($key)) {
                foreach ($element->childNodes as $child) {
                    if (strtolower($child->nodeName) === $value) {
                        $item[$value] = $child->nodeValue;
                        break;
                    }
                }
            } else if (is_array($value)) {
                $subitems = [];

                foreach ($element->childNodes as $child) {
                    if (strtolower($child->nodeName) === $key) {
                        $subitems[] = $this->mapDOMElement($child, $value);
                    }
                }

                $item[$key] = $subitems;
            }
        }

        return $item;
    }
}
