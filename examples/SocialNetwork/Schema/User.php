<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;

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

    public function initRelations() : self
    {
        $this->addRelation('addresses', Address::class, 'id', 'id_user', true);
        $this->addRelation('posts', Post::class, 'id', 'id_user', true);
        return $this;
    }
}
