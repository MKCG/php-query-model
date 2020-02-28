<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\HttpResponse;
use MKCG\Model\DBAL\Mapper\Xml;

class SitemapReader extends Http
{
    use ContentFilterTrait;

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

            $elements[] = Xml::mapDOMElement($node, $query->fields);
        }

        $elements = array_filter($elements, function($item) use ($query) {
            return $this->matchQuery($item, $query);
        });

        return $elements;
    }
}
