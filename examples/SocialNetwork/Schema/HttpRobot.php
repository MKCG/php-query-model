<?php

namespace MKCG\Examples\SocialNetwork\Schema;

use MKCG\Model\GenericSchema;
use MKCG\Model\GenericEntity;
use MKCG\Model\Configurations\Http;

class HttpRobot extends GenericSchema
{
    protected $driverName = 'http_robot';
    protected $entityClass = GenericEntity::class;

    protected $types = [
        'default' => [
            'User-Agent' => [
                'Name',
                'Allow',
                'Disallow',
            ],
            'Sitemap'
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
