<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\FilterInterface;

class HackerNewsStory extends GenericSchema
{
    protected $driverName = 'http';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['id'];

    protected $types = [
        'default' => [
            'id',
            'deleted',
            'type',
            'by',
            'time',
            'text',
            'dead',
            'parent',
            'poll',
            'kids',
            'url',
            'score',
            'title',
            'parts',
            'descendants'
        ],
        'homepage' => [
            'id',
            'type',
            'by',
            'time',
            'title',
            'text',
            'url'
        ]
    ];

    public static function queryUrlGenerator(Query $query)
    {
        if (!isset($query->filters['id'][FilterInterface::FILTER_IN])) {
            throw new \Exception("Missing id filter");
        }

        $id = filter_var($query->filters['id'][FilterInterface::FILTER_IN], FILTER_VALIDATE_INT);

        if ($id === false) {
            throw new \Exception("Invalid id");
        }

        return 'https://hacker-news.firebaseio.com/v0/item/' . $id . '.json';
    }
}
