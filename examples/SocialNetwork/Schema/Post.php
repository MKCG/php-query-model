<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class Post extends GenericSchema
{
    protected $tableName = 'socialnetwork.post';
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

    protected $relations = [
        'author' => [
            'schema' => User::class,
            'match' => [
                'id_user' => 'id'
            ],
            'isCollection' => false
        ]
    ];
}

