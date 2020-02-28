<?php

namespace MKCG\Model\Configurations;

class Http
{
    private $headers = [];
    private $url = '';
    private $uri = '';
    private $method = '';
    private $options = [];

    public function getHeaders() : array
    {
        return $this->headers;
    }

    public function getUrl() : string
    {
        return $this->url;
    }

    public function getUri() : string
    {
        return $this->uri;
    }

    public function getMethod() : string
    {
        return $this->method;
    }

    public function getOptions() : array
    {
        return $this->options;
    }

    public function addHeader(string $name, string $value) : self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function setMethod(string $method) : self
    {
        $this->method = $method;
        return $this;
    }

    public function setUrl(string $url) : self
    {
        $this->url = $url;
        return $this;
    }

    public function setUri(string $uri) : self
    {
        $this->uri = $uri;
        return $this;
    }
}
