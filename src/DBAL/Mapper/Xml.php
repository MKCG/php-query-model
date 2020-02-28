<?php

namespace MKCG\Model\DBAL\Mapper;

class Xml
{
    public static function mapSimpleXMLElement(\SimpleXMLElement $node, array $fields) : ?array
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
                    $subitems[] = self::mapSimpleXMLElement($element, $value);
                }

                $item[$key] = $subitems;
            }
        }

        return $item;
    }

    public static function mapDOMElement(\DOMElement $element, array $fields)
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
                        $subitems[] = self::mapDOMElement($child, $value);
                    }
                }

                $item[$key] = $subitems;
            }
        }

        return $item;
    }
}
