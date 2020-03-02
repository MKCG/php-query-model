<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\FieldInterface;

class SearchableCustomerOrder extends GenericSchema
{
    protected $name = 'customers_orders';
    protected $driverName = 'redisearch';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['order_id'];

    protected $types = [
        'default' => [
            'order_id',
            'price',
            'vat',
            'currency',
            'credit_card_type',
            'credit_card_number',
            'customer_id',
            'customer_firstname',
            'customer_lastname',
            'customer_email',
            'products_ids',
            'addresses_ids',
            'addresses_countries',
        ],
    ];

    public function initRelations() : self
    {
        $this->addRelation('customer', User::class, 'customer_id', 'id', false);
        return $this;
    }

    protected function initFields() : self
    {
        return $this
            ->setFieldDefinition('order_id', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('price', FieldInterface::TYPE_FLOAT, true, true, true)
            ->setFieldDefinition('vat', FieldInterface::TYPE_FLOAT, true, true, true)
            ->setFieldDefinition('currency', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('credit_card_type', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('credit_card_number', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('customer_id', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('customer_firstname', FieldInterface::TYPE_STRING, true)
            ->setFieldDefinition('customer_lastname', FieldInterface::TYPE_STRING, true)
            ->setFieldDefinition('customer_email', FieldInterface::TYPE_STRING, true)
            ->setFieldDefinition('products_ids', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('addresses_ids', FieldInterface::TYPE_ENUM, true)
            ->setFieldDefinition('addresses_countries', FieldInterface::TYPE_STRING, true)
        ;
    }
}
