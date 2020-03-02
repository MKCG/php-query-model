<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\FieldInterface;

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

    protected function initFields() : self
    {
        return $this
            ->setFieldDefinition('_id', FieldInterface::TYPE_INT, true)
            ->setFieldDefinition('name', FieldInterface::TYPE_STRING, true)
            ->setFieldDefinition('society', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('sku.color', FieldInterface::TYPE_STRING, true, true, true)
            ->setFieldDefinition('sku.isbn13', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('sku.country', FieldInterface::TYPE_STRING, true, true, true)
        ;
    }
}
