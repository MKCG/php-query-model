<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class Order extends GenericSchema
{
    protected $name = 'orders.csv';
    protected $driverName = 'csv';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['id'];

    protected $types = [
        'default' => [
            'id',
            'id_user',
            'firstname',
            'lastname',
            'credit_card_type',
            'credit_card_number',
            'price',
            'vat',
            'currency'
        ],
    ];

    protected $relations = [
        'customer' => [
            'schema' => User::class,
            'match' => [
                'id_user' => 'id'
            ],
            'isCollection' => false
        ],
    ];
}
