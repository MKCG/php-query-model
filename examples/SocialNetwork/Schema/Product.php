<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

class Product extends GenericSchema
{
    protected $driverName = 'mongodb';
    protected $name = 'ecommerce.product';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['_id'];

    protected $types = [
        'default' => [
            '_id',
            'name',
            'society',
            'sku' => [
                'color',
                'isbn13',
                'country'
            ]
        ],
    ];
}
