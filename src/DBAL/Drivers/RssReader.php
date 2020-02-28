<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\HttpResponse;
use MKCG\Model\DBAL\Mapper\Xml;

class RssReader extends Http
{
    use ContentFilterTrait;

    protected function makeResultList(Query $query, HttpResponse $response) : array
    {
        if (empty($response->body)) {
            return [];
        }

        $xml = new \SimpleXMLElement($response->body);

        $channels = $xml->xpath('//rss/channel');
        $fields = $query->fields;

        $results = array_map(function($node) use ($fields) {
            return Xml::mapSimpleXMLElement($node, $fields);
        }, $channels);

        $results = array_filter($results, function($item) use ($query) {
            return $this->matchQuery($item, $query);
        });

        return $results;
    }
}
