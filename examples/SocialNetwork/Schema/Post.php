<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class Post extends GenericSchema
{
    protected $name = 'socialnetwork.post';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['id'];

    protected $types = [
        'default' => [
            'id',
            'id_user',
            'published_at',
            'title',
            'content',
        ],
    ];

    public function initRelations() : self
    {
        $this->addRelation('author', User::class, ['id_user' => 'id'], false);
        return $this;
    }

    public static function applyPublicFilters(array $args)
    {

    }
}
