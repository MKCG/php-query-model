<?php

namespace MKCG\Model\DBAL\Drivers;

use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\HttpResponse;

class HttpRobot extends Http
{
    protected function makeResultList(Query $query, HttpResponse $response) : array
    {
        if (empty($response->body)) {
            return [];
        }

        $content = explode("\n", $response->body);
        $content = array_map('trim', $content);
        $content = array_filter($content, function($line) {
            return $line !== '' && strpos($line, '#') !== 0;
        });

        $content = array_map(function($line) {
            $pos = strpos($line, ':');

            return $pos > 0
                ? [ strtolower(trim(substr($line, 0, $pos))) , trim(substr($line, $pos + 1)) ]
                : [];
        }, $content);

        $sitemaps = [];
        $agents = [];

        $currentAgent = '';
        $allowed = [];
        $disallowed = [];

        foreach ($content as $line) {
            if ($line === []) {
                continue;
            }

            switch ($line[0]) {
                case 'user-agent':
                    if ($currentAgent !== '') {
                        $agents[] = [
                            'User-Agent' => $currentAgent,
                            'Allow' => $allowed,
                            'Disallow' => $disallowed
                        ];
                    }

                    $currentAgent = $line[1];
                    $allowed = [];
                    $disallowed = [];
                    break;

                case 'allow':
                    $allowed[] = $line[1];
                    break;

                case 'disallow':
                    $disallowed[] = $line[1];
                    break;

                case 'sitemap':
                    $sitemaps[] = $line[1];
                    break;
            }
        }

        if ($currentAgent !== '') {
            $agents[] = [
                'User-Agent' => $currentAgent,
                'Allow' => $allowed,
                'Disallow' => $disallowed
            ];
        }

        $robot = [
            'User-Agent' => $agents,
            'Sitemap' => $sitemaps
        ];

        return [ $robot ];
    }
}
