<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\FieldInterface;

class User extends GenericSchema
{
    protected $name = 'socialnetwork.user';
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

    protected function initRelations()
    {
        $this->addRelation('addresses', Address::class, 'id', 'id_user', true);
        $this->addRelation('posts', Post::class, 'id', 'id_user', true);
        return $this;
    }

    protected function initFields()
    {
        return $this
            ->setFieldDefinition('id', FieldInterface::TYPE_INT, true)
            ->setFieldDefinition('firstname', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('lastname', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('email', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('phone', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('registered_at', FieldInterface::TYPE_DATETIME)
            ->setFieldDefinition('status', FieldInterface::TYPE_INT)
        ;
    }
}
