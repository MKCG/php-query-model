<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\HttpResponse;
use MKCG\Model\DBAL\Mapper\Xml;
use MKCG\Model\DBAL\Filters\ContentFilter;

class SitemapReader extends Http
{
    public function getSupportedOptions() : array
    {
        return array_merge(parent::getSupportedOptions(), ['case_sensitive']);
    }

    protected function makeResultList(Query $query, HttpResponse $response) : array
    {
        if (empty($response->body)) {
            return [];
        }

        $elements = [];

        $dom = \DOMDocument::loadXML($response->body);

        if ($dom->childNodes->count() === 0) {
            return [];
        }

        $isUrlset = strtolower($dom->childNodes[0]->nodeName) === 'urlset';
        $isSitemapIndex = strtolower($dom->childNodes[0]->nodeName) === 'sitemapindex';

        foreach ($dom->childNodes[0]->childNodes as $node) {
            if ($isUrlset && strtolower($node->nodeName) !== 'url') {
                continue;
            } else if ($isSitemapIndex && strtolower($node->nodeName) !== 'sitemap') {
                continue;
            }

            $elements[] = Xml::mapDOMElement($node, $query->fields);
        }

        $elements = array_filter($elements, function($item) use ($query) {
            return ContentFilter::matchQuery($item, $query);
        });

        return $elements;
    }
}
