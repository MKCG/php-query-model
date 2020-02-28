<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class HackerNewsTopStory extends GenericSchema
{
    protected $driverName = 'http';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['id'];

    protected $types = [
        'default' => [
            'id',
        ],
    ];

    public function initRelations() : self
    {
        $this->addRelation('story', HackerNewsStory::class, ['id' => 'id'], false);
        return $this;
    }

    public static function httpJsonFormatter($json)
    {
        if (!is_array($json)) {
            throw new \Exception("Invalid JSON content");
        }

        return array_map(function($id) {
            return ['id' => $id];
        }, $json);
    }
}
