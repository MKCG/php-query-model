<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\HttpResponse;

class SitemapReader extends Http
{
    protected function makeResultList(Query $query, HttpResponse $response) : array
    {
        if (empty($response->body)) {
            return [];
        }

        $elements = [];

        $dom = \DOMDocument::loadXML($response->body);

        if ($dom->childNodes->count() === 0 || strtolower($dom->childNodes[0]->nodeName) !== 'urlset') {
            return [];
        }

        foreach ($dom->childNodes[0]->childNodes as $node) {
            if (strtolower($node->nodeName) !== 'url') {
                continue;
            }

            $elements[] = $this->mapDOMElement($node, $query->fields);
        }

        return $elements;
    }
}
