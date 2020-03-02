<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\FieldInterface;

class Post extends GenericSchema
{
    protected $name = 'socialnetwork.post';
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

    public function initRelations() : self
    {
        $this->addRelation('author', User::class, 'id_user', 'id', false);
        return $this;
    }

    protected function initFields() : self
    {
        return $this
            ->setFieldDefinition('id', FieldInterface::TYPE_INT, true, true)
            ->setFieldDefinition('id_user', FieldInterface::TYPE_INT, true, true)
            ->setFieldDefinition('published_at', FieldInterface::TYPE_DATETIME)
            ->setFieldDefinition('title', FieldInterface::TYPE_STRING)
            ->setFieldDefinition('content', FieldInterface::TYPE_STRING)
        ;
    }
}
