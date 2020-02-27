<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class User extends GenericSchema
{
    protected $tableName = 'socialnetwork.user';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['id'];

    protected $types = [
        'default' => [
            'id',
            'firstname',
            'lastname',
            'email',
            'phone',
            'registered_at',
            'status'
        ],
    ];

    protected $relations = [
        'addresses' => [
            'schema' => Address::class,
            'match' => [
                'id' => 'id_user'
            ],
            'isCollection' => true
        ],
        'posts' => [
            'schema' => Post::class,
            'match' => [
                'id' => 'id_user'
            ],
            'isCollection' => true
        ],
    ];
}
