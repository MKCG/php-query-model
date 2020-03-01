<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\Configurations;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Result;
use MKCG\Model\DBAL\HttpRequest;
use MKCG\Model\DBAL\HttpResponse;
use MKCG\Model\DBAL\ScrollContext;
use MKCG\Model\DBAL\Mapper;
use MKCG\Model\DBAL\Drivers\Adapters\HttpClientInterface;

class Http implements DriverInterface
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

    public function getSupportedOptions() : array
    {
        return [
            'max_query_time',
            'json_formatter',
            'url',
            'uri',
            'url_generator',
            'method',
            'headers',
            'options',
        ];
    }

    public function search(Query $query) : Result
    {
        $content = [];

        if (!empty($query->context['scroll']->data['content'])) {
            $content = array_slice(
                $query->context['scroll']->data['content'],
                $query->offset,
                $query->limit
            );

            if ($query->offset + $query->limit > $query->context['scroll']->data['count']) {
                $query->context['scroll']->stop();
            }
        } else {
            $body = $this->makeRequestBody($query);
            $response = $this->getResponse($query, $body);
            $content = $response->statusCode >= 200 && $response->statusCode < 300
                ? $this->makeResultList($query, $response)
                : [];

            if (!empty($query->context['scroll'])) {
                $query->context['scroll']->data['content'] = $content;
                $query->context['scroll']->data['count'] = count($content);
            }

            if ($query->limit > 0 && count($content) > $query->limit) {
                $content = array_slice($content, $query->offset, $query->limit);
            } else if (!empty($query->context['scroll'])) {
                $query->context['scroll']->stop();
            }
        }

        return Result::make($content, $query->entityClass);
    }

    protected function makeRequestBody(Query $query)
    {
        return null;
    }

    protected function makeResultList(Query $query, HttpResponse $response) : array
    {
        $type = explode(';', strtolower($response->contentType));
        $type = array_map('trim', $type);
        $type = array_intersect($type, ['application/json', 'text/html', 'text/xml']);
        $type = array_shift($type) ?? '';

        $type = [
            '' => '',
            'application/json' => 'json',
            'text/html' => 'html',
            'text/xml' => 'xml'
        ][$type];

        switch ($type) {
            case 'json':
                $json = json_decode($response->body, JSON_OBJECT_AS_ARRAY);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Invalid JSON response : " . json_last_error_msg());
                }

                if (!empty($query->context['options']['json_formatter'])) {
                    if (is_callable($query->context['options']['json_formatter'])) {
                        return call_user_func($query->context['options']['json_formatter'], $json, $query->fields);
                    } else if (is_array($query->context['options']['json_formatter'])
                        && isset(
                            $query->context['options']['json_formatter'][0],
                            $query->context['options']['json_formatter'][1]
                        )
                    ) {
                        return call_user_func(
                            $query->context['options']['json_formatter'],
                            $json,
                            $query->fields
                        );
                    } else {
                        throw new \Exception("Invalid json_formatter provided");
                    }
                }

                return [ Mapper\Json::mapItem($json, $query->fields) ];

            case 'html':
                return [];

            default:
                return [];
        }
    }

    private function getResponse(Query $query, $body = null)
    {
        if (!empty($query->context['http']) && !is_a($query->context['http'], Configurations\Http::class)) {
            throw new \Exception("Invalid HTTP configuration provided");
        }

        $configuration = $query->context['http'] ?: new Configurations\Http();

        $options = $query->context['options'] ?: [];

        $request = new HttpRequest();
        $request->uri = $options['uri'] ?: ($configuration->getUri() ?: $this->defaultUri);
        $request->method = $options['method'] ?: ($configuration->getMethod() ?: $this->defaultMethod);
        $request->headers = $options['headers'] ?: ($configuration->getHeaders() ?: $this->defaultHeaders);
        $request->options = $options['options'] ?: ($configuration->getOptions() ?: $this->defaultOptions);

        $request->url = !empty($options['url_generator'])
            ? call_user_func($options['url_generator'], $query)
            : ($options['url']
                ?: ($configuration->getUrl()
                    ?: $this->defaultUrl
                )
            );

        $request->url = filter_var($request->url, FILTER_VALIDATE_URL);

        if (empty($request->url)) {
            throw new \Exception("Empty URL provided");
        }

        return $this->client->sendRequest($request);
    }
}
