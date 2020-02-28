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

    public function initConfigurations() : self
    {
        $this->addConfiguration('http', (new Http())
                ->addHeader('User-Agent', 'Kévin Masseix | Looking for opportunities | https://github.com/MKCG')
                ->setUrl('https://www.sitemaps.org/sitemap.xml')
            );

        return $this;
    }
}
