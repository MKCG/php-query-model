<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\Configurations\Http;

class Rss extends GenericSchema
{
    protected $driverName = 'rss';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['guid'];

    protected $types = [
        'default' => [
            'title',
            'description',
            'pubDate',
            'generator',
            'link',
            'item' => [
                'title',
                'description',
                'pubDate',
                'link',
                'guid',
            ]
        ],
    ];

    public function initConfigurations() : self
    {
        $this->configurations = [
            'http' => (new Http())
                ->addHeader('User-Agent', 'Kévin Masseix | Looking for opportunities | https://github.com/MKCG')
        ];

        return $this;
    }
}
