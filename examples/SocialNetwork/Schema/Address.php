<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class Address extends GenericSchema
{
    protected $name = 'socialnetwork.address';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['id'];

    protected $types = [
        'default' => [
            'id',
            'id_user',
            'street',
            'postcode',
            'city',
            'country',
        ],
    ];

    protected $relations = [
        'owner' => [
            'schema' => User::class,
            'match' => [
                'id_user' => 'id'
            ],
            'isCollection' => false
        ]
    ];
}
