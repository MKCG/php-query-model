<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\Configurations\Http;

class Sitemaps extends GenericSchema
{
    protected $driverName = 'sitemap';
    protected $entityClass = GenericEntity::class;
    protected $primaryKeys = ['loc'];

    protected $types = [
        'default' => [
            'loc',
            'lastmod',
            'news:news' => [
                'news:publication_date',
                'news:title',
                'news:publication' => [
                    'news:name',
                    'news:language',
                ]
            ],
            'image:image' => [
                'image:loc',
                'image:caption',
            ]
        ],
    ];

    protected function initConfigurations()
    {
        $this->addConfiguration('http', (new Http())
                ->addHeader('User-Agent', 'Kévin Masseix | Looking for opportunities | https://github.com/MKCG')
            );

        return $this;
    }
}
