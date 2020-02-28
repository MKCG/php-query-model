<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\HttpResponse;

class RssReader extends Http
{
    protected function makeResultList(Query $query, HttpResponse $response) : array
    {
        if (empty($response->body)) {
            return [];
        }

        $xml = new \SimpleXMLElement($response->body);

        $channels = $xml->xpath('//rss/channel');
        $fields = $query->fields;

        return array_map(function($node) use ($fields) {
            return $this->mapSimpleXMLElement($node, $fields);
        }, $channels);
    }
}
