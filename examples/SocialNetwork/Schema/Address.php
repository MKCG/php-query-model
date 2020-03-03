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

    protected function initRelations()
    {
        $this->addRelation('owner', User::class, 'id_user', 'id', false);
        return $this;
    }
}
