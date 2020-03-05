<?php

require __DIR__ . '/vendor/autoload.php';

use MKCG\Examples\SocialNetwork\Schema;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\AggregationInterface;
use MKCG\Model\DBAL\QueryCriteria;
use MKCG\Model\DBAL\QueryEngine;
use MKCG\Model\DBAL\Query;
use MKCG\Model\DBAL\Drivers;
use MKCG\Model\DBAL\Drivers\Adapters;
use MKCG\Model\DBAL\CallableOptionValidator;
use MKCG\Model\ETL;

use Ehann\RedisRaw\PredisAdapter;

$mongoClient = new MongoDB\Client('mongodb://root:password@mongodb');
$redisClient = (new PredisAdapter())->connect('redisearch', 6379);

$sqlConnection = \Doctrine\DBAL\DriverManager::getConnection([
    'user' => 'root',
    'password' => 'root',
    'host' => 'mysql',
    'driver' => 'pdo_mysql',
]);

$fixturePath = __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';

$engine = (new QueryEngine('mysql'))
    ->registerDriver(new Drivers\Doctrine($sqlConnection), 'mysql')
    ->registerDriver(new Drivers\CsvReader($fixturePath), 'csv')
    ->registerDriver(new Drivers\RssReader(new Adapters\Guzzle), 'rss')
    ->registerDriver(new Drivers\SitemapReader(new Adapters\Guzzle), 'sitemap')
    ->registerDriver(new Drivers\Http(new Adapters\Guzzle), 'http')
    ->registerDriver(new Drivers\HttpRobot(new Adapters\Guzzle), 'http_robot')
    ->registerDriver(new Drivers\MongoDB($mongoClient), 'mongodb')
    ->registerDriver(new Drivers\Redisearch($redisClient), 'redisearch')
;

createFakeData($sqlConnection, $mongoClient, $redisClient, $fixturePath . DIRECTORY_SEPARATOR, $engine);

$startedAt = microtime(true);

searchCustomersOrders($engine);
pipelineEtl($engine);
searchOrder($engine);
// searchProducts($engine);
// searchGithubRobot($engine);
// searchSitemaps($engine);
// searchPackages($engine);
// searchUsers($engine);
// searchHackerNews($engine);

$took = microtime(true) - $startedAt;
echo "Took : " . round($took, 3) . "s\n";

function searchCustomersOrders(QueryEngine $engine)
{
    $model = Schema\SearchableCustomerOrder::make('default', 'customers_orders');
    $criteria = (new QueryCriteria())
        ->forCollection('customers_orders')
            ->addFilter('price', FilterInterface::FILTER_GREATER_THAN, 15)
            ->addFilter('addresses_countries', FilterInterface::FILTER_FULLTEXT_MATCH, 'Mexico')
            ->addAggregation(AggregationInterface::FACET, ['field' => 'currency', 'limit' => 3])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'vat', 'limit' => 3])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'credit_card_type', 'limit' => 3])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'customer_id', 'limit' => 3])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'price', 'limit' => 3])
            ->addAggregation(AggregationInterface::TERMS, ['field' => 'price', 'limit' => 3])
            ->addAggregation(AggregationInterface::AVERAGE, ['field' => 'price', 'decimal' => 3])
            ->addAggregation(AggregationInterface::MIN, ['field' => 'price', 'decimal' => 3])
            ->addAggregation(AggregationInterface::MAX, ['field' => 'price', 'decimal' => 3])
            ->addAggregation(AggregationInterface::QUANTILE, ['field' => 'price', 'quantile' => [ 5, 10, 20, 50, 80, 90, 95 ]])
            ->setLimit(10)
        ;

    $count = 0;

    foreach ($engine->scroll($model, $criteria, 100) as $customerOrder) {
        $count++;
    }

    echo sprintf("Scrolled over %d customer orders\n", $count);

    $results = $engine->query($model, $criteria);

    echo json_encode($results, JSON_PRETTY_PRINT) . "\n\n";
}


function pipelineEtl(QueryEngine $engine)
{
    $model = Schema\Product::make('default', 'products');
    $criteria = (new QueryCriteria())
        ->forCollection('products')
            ->addFilter('sku.color', FilterInterface::FILTER_IN, ['aqua', 'purple'])
        ;

    $pushed = ETL::extract($engine->scroll($model, $criteria, 200), 1000, 500)
        ->transform(function($item) {
            return [
                'id' => $item['_id'],
                'sku' => $item['sku']
            ];
        })
        ->transform(function($item) {
            return $item + [
                'sku_count' => count($item['sku'] ?? [])
            ];
        })
        ->load(function(iterable $bulk) {
            echo sprintf("[ETL] Loader 1 - Loading %d elements\n", count($bulk));
        })
        ->load(function(iterable $bulk) {
            echo sprintf("[ETL] Loader 2 - Loading %d elements\n", count($bulk));
        })
        ->load(function(iterable $bulk) {
            echo sprintf("[ETL] Loader 3 - Loading %d elements\n", count($bulk));
        })
        ->run();

    echo sprintf("[ETL] Pushed %d elements\n", $pushed);
}

function searchProducts(QueryEngine $engine)
{
    $model = Schema\Product::make('default', 'products');

    $criteria = (new QueryCriteria())
        ->forCollection('products')
            ->addFilter('sku.color', FilterInterface::FILTER_IN, ['aqua', 'purple'])
            ->addFilter('sku.color', FilterInterface::FILTER_NOT_IN, 'black')
            ->addFilter('name', FilterInterface::FILTER_FULLTEXT_MATCH, 'mr')
            ->addCallableFilter(function(Query $query, array $filters) {
                $filters['$text']['$language'] = 'english';
                $filters['society'] = [
                    '$nin' => ["Harris and Sons", "Hermann-Schmidt"]
                ];

                return $filters;
            })
            ->addAggregation(AggregationInterface::AVERAGE, ['field' => '_id', 'decimal' => 2])
            ->addAggregation(AggregationInterface::TERMS, ['field' => 'society'])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'society'])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'sku.color'])
            ->addAggregation(AggregationInterface::MIN, ['field' => 'society'])
            ->addAggregation(AggregationInterface::MAX, ['field' => 'society'])
            ->addAggregation(AggregationInterface::QUANTILE, ['field' => 'society', 'quantile' => [ 5, 10, 20, 50, 80, 90, 95 ]])
            ->addOption('case_sensitive', false)
            ->addSort('sku.color', 'DESC')
            ->addSort('name', 'ASC')
            ->addOption('max_query_time', 100)
            ->addOption('allow_partial', true)
            ->setOffset(25)
            ->setLimit(100)
    ;

    $found = 0;

    foreach ($engine->scroll($model, $criteria, 30) as $product) {
        echo json_encode($product, JSON_PRETTY_PRINT) . "\n\n";
        $found++;
    }

    echo "Products scrolled : " . $found . "\n\n";

    $results = $engine->query($model, $criteria);
    echo json_encode($results, JSON_PRETTY_PRINT) . "\n\n";
}

function searchGithubRobot(QueryEngine $engine)
{
    $model = Schema\HttpRobot::make('default', 'robots.txt');
    $criteria = (new QueryCriteria())
        ->forCollection('robots.txt')
            ->addOption('url', 'https://github.com/robots.txt')
            ->addOption('max_query_time', 500)
    ;

    foreach ($engine->scroll($model, $criteria) as $userAgent) {
        echo json_encode($userAgent, JSON_PRETTY_PRINT) . "\n\n";
    }
}

function searchHackerNews(QueryEngine $engine)
{
    $model = Schema\HackerNewsTopStory::make('default', 'hn')
        ->with(Schema\HackerNewsStory::make('homepage'))
    ;

    $criteria = (new QueryCriteria())
        ->forCollection('hn')
            ->addOption('url', 'https://hacker-news.firebaseio.com/v0/topstories.json')
            ->addOption('json_formatter', [ Schema\HackerNewsTopStory::class , 'httpJsonFormatter' ])
        ->forCollection('story')
            ->addOption('multiple_requests', true)
            ->addOption('url_generator', [ Schema\HackerNewsStory::class , 'queryUrlGenerator' ])
        ;

    foreach ($engine->scroll($model, $criteria, 1) as $story) {
        echo json_encode($story, JSON_PRETTY_PRINT) . "\n\n";
    }
}

function searchSitemaps(QueryEngine $engine)
{
    $model = Schema\Sitemaps::make('default', 'sitemap');
    $criteria = (new QueryCriteria())
        ->forCollection('sitemap')
            ->addOption('url', 'https://www.sitemaps.org/sitemap.xml')
            ->addFilter('loc', FilterInterface::FILTER_FULLTEXT_MATCH, 'faq')
        ;

    foreach ($engine->scroll($model, $criteria) as $url) {
        echo json_encode($url, JSON_PRETTY_PRINT) . "\n\n";
    }
}

function searchPackages(QueryEngine $engine)
{
    $model = Schema\Rss::make('default', 'rss');
    $criteria = (new QueryCriteria())
        ->forCollection('rss')
            ->addOption('url', 'https://packagist.org/feeds/packages.rss')
        ;

    foreach ($engine->scroll($model, $criteria) as $channel) {
        echo json_encode($channel, JSON_PRETTY_PRINT) . "\n\n";
    }
}

function searchOrder(QueryEngine $engine)
{
    $model = Schema\Order::make('default', 'order')
        ->with(Schema\Product::make())
        ->with(Schema\User::make()
            ->with(Schema\Address::make())
            ->with(Schema\Post::make())
        )
    ;

    $criteria = (new QueryCriteria())
        ->forCollection('order')
            ->addOption('filepath', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'orders.csv')
            ->addOption('filepath', 'orders.csv')
            ->addOption('case_sensitive', false)
            ->addFilter('firstname', FilterInterface::FILTER_FULLTEXT_MATCH, 'al')
            ->addFilter('price', FilterInterface::FILTER_GREATER_THAN_EQUAL, 15)
            ->addFilter('price', FilterInterface::FILTER_GREATER_THAN, 10)
            ->addFilter('vat', FilterInterface::FILTER_IN, [ 10, 20 ])
            ->addFilter('credit_card_type', FilterInterface::FILTER_NOT_IN, ['Visa', 'Visa Retired'])
            ->addCallableFilter(function(Query $query, array $rawOrder) {
                return $rawOrder['firstname'] !== $rawOrder['lastname'];
            })
            ->addCallableFilter(function(Query $query, array $rawOrder) {
                return $rawOrder['price'] !== $rawOrder['vat'];
            })
            ->addAggregation(AggregationInterface::MIN, ['field' => 'price', 'decimal' => 3])
            ->addAggregation(AggregationInterface::MAX, ['field' => 'price', 'decimal' => 3])
            ->addAggregation(AggregationInterface::AVERAGE, ['field' => 'price', 'decimal' => 3])
            ->setOffset(1)
            ->setLimit(2)
        ->forCollection('addresses')
            ->addCallableFilter(function(Query $query, \Doctrine\DBAL\Query\QueryBuilder $queryBuilder) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->neq('city', 'country')
                );
            })
            ->setLimitByParent(3)
        ->forCollection('posts')
            ->setLimitByParent(2)
        ;

    $orders = [];

    echo json_encode($engine->query($model, $criteria), JSON_PRETTY_PRINT) . "\n\n";

    foreach ($engine->scroll($model, $criteria, 3) as $i => $order) {
    // foreach ($engine->query($model, $criteria)->getContent() as $i => $order) {
        $orders[] = $order;
    }

    echo json_encode($orders, JSON_PRETTY_PRINT) . "\n\n";
    echo "Found : " . count($orders) . " items\n\n";
}

function searchUsers(QueryEngine $engine)
{
    $model = Schema\User::make('default', 'user')
        // ->with(Schema\Address::make())
        // ->with(Schema\Post::make())
    ;

    $criteria = (new QueryCriteria())
        ->forCollection('user')
            ->addFilter('status', FilterInterface::FILTER_IN, [ 2 , 3 , 5 , 7 ])
            ->addFilter('registered_at', FilterInterface::FILTER_GREATER_THAN_EQUAL, '2000-01-01')
            ->addAggregation(AggregationInterface::FACET, ['field' => 'status', 'limit' => 5])
            ->addAggregation(AggregationInterface::FACET, ['field' => 'lastname', 'limit' => 5])
            ->addAggregation(AggregationInterface::TERMS, ['field' => 'status', 'offset' => 1, 'limit' => 2])
            ->addAggregation(AggregationInterface::AVERAGE, ['field' => 'status', 'decimal' => 2])
            ->addAggregation(AggregationInterface::MIN, ['field' => 'firstname'])
            ->addAggregation(AggregationInterface::MAX, ['field' => 'firstname'])
            ->addAggregation(AggregationInterface::QUANTILE, ['field' => 'firstname', 'quantile' => [ 10 , 40 , 60 , 90 ]])
            ->addSort('firstname', 'ASC')
            ->addSort('lastname', 'ASC')
            ->setLimit(10)
        ->forCollection('addresses')
            ->setLimitByParent(2)
        ->forCollection('posts')
            ->addFilter('title', FilterInterface::FILTER_FULLTEXT_MATCH, 'ab')
    ;

    $users = $engine->query($model, $criteria);
    echo json_encode($users, JSON_PRETTY_PRINT) . "\n\n";
    echo "\nFound : " . $users->getCount() . " users\n";

    $iterator = $engine->scroll($model, $criteria);

    foreach ($iterator as $i => $user) {
        echo json_encode($user, JSON_PRETTY_PRINT) . "\n";
    }
}

function createFakeData(\Doctrine\DBAL\Connection $connection, \MongoDB\Client $mongo, PredisAdapter $redisClient, string $fixturePath, QueryEngine $engine)
{
    $faker = \Faker\Factory::create();

    $productCounter = 10000;
    $products = [];

    $productCollection = $mongo->ecommerce->product;
    $productCollection->drop();

    $productCollection->createIndex(['sku.color' => 1]);
    $productCollection->createIndex(['sku.country' => 1]);
    $productCollection->createIndex(['name' => 'text']);

    for ($i = 1; $i <= $productCounter; $i++) {
        $product = [
            '_id' => $i,
            'name' => $faker->title,
            'society' => $faker->company,
            'sku' => array_map(function($i) use ($faker) {
                return [
                    'color' => $faker->safeColorName,
                    'isbn13' => $faker->isbn13,
                    'country' => $faker->countryCode
                ];
            }, range(0, rand(1, 5)))
        ];

        $products[] = $product;

        if ($i % 500 === 0 && $products !== []) {
            $productCollection->insertMany($products);
            echo "Created : ${i}/${productCounter} products\n";
            $products = [];
        }
    }

    if ($products !== []) {
        echo "Created : ${i}/${productCounter} products\n";
        $productCollection->insertMany($products);
        $products = [];
    }

    createDatabaseSchema($connection, [ new Schema\User(), new Schema\Address(), new Schema\Post() ]);

    $csvOrderHandler = fopen($fixturePath . 'orders.csv', 'w+');
    fputcsv($csvOrderHandler, [
        'id',
        'id_user',
        'firstname',
        'lastname',
        'credit_card_type',
        'credit_card_number',
        'price',
        'vat',
        'currency',
        'product_ids'
    ]);

    $userCounter = 0;
    $addressCounter = 0;
    $postCounter = 0;
    $orderCounter = 0;

    $statements = '';

    for ($i = 1; $i <= 1000; $i++) {
        $user = [
            'id' => ++$userCounter,
            'firstname' => $faker->firstName,
            'lastname' => $faker->lastName,
            'email' => $faker->email,
            'phone' => $faker->e164PhoneNumber,
            'registered_at' => $faker->date,
            'status' => $faker->numberBetween(0, 10)
        ];

        for ($j = 0; $j < 20; $j++) {
            $productIds = range(0, rand(0, 5));
            $productIds = array_map(function($i) use ($productCounter) {
                return rand(1, $productCounter < 1 ? 1 : $productCounter -1);
            }, $productIds);
            $productIds = array_unique($productIds);
            sort($productIds);

            fputcsv($csvOrderHandler, [
                ++$orderCounter,
                $user['id'],
                $user['firstname'],
                $user['lastname'],
                $faker->creditCardType,
                $faker->creditCardNumber,
                rand(1, 100),
                [5, 10, 20][rand(0, 2)],
                $faker->currencyCode,
                implode(',', $productIds)
            ]);

            if (rand(0, 10) < 3) {
                break;
            }
        }

        $query = sprintf(
            "INSERT INTO socialnetwork.user
                (id, firstname, lastname, email, phone, registered_at, status)
            VALUES (%d , %s , %s, %s, %s , %s, %d );",
            $user['id'],
            $connection->quote($user['firstname']),
            $connection->quote($user['lastname']),
            $connection->quote($user['email']),
            $connection->quote($user['phone']),
            $connection->quote($user['registered_at']),
            $user['status']
        );

        $statements .= $query . "\n";
        // $connection->exec($query);

        for ($j = 0; $j < 5; $j++) {
            $address = [
                'id' => ++$addressCounter,
                'id_user' => $i,
                'street' => $faker->streetName,
                'postcode' => $faker->postcode,
                'city' => $faker->city,
                'country' => $faker->country
            ];

            $query = sprintf(
                "INSERT INTO socialnetwork.address
                    (id, id_user, street, postcode, city, country)
                VALUES (%d , %d , %s, %s, %s, %s);",
                $address['id'],
                $address['id_user'],
                $connection->quote($address['street']),
                $connection->quote($address['postcode']),
                $connection->quote($address['city']),
                $connection->quote($address['country']),
            );

            $statements .= $query . "\n";
            // $connection->exec($query);

            if (rand(0, 5) < 2) {
                break;
            }
        }

        for ($k = 0; $k < 10; $k++) {
            $post = [
                'id' => ++$postCounter,
                'id_user' => $userCounter,
                'published_at' => $faker->date,
                'title' => $faker->sentence,
                'content' => $faker->paragraphs(rand(3, 6), true)
            ];

            $query = sprintf(
                "INSERT INTO socialnetwork.post
                    (id, id_user, published_at, title, content)
                VALUES (%d , %d , %s, %s, %s);",
                $post['id'],
                $post['id_user'],
                $connection->quote($post['published_at']),
                $connection->quote($post['title']),
                $connection->quote($post['content'])
            );

            $statements .= $query . "\n";


            if (rand(0, 10) < 2) {
                break;
            }
        }

        if ($i % 50 === 0) {
            $connection->exec($statements);
            echo "Created : ${userCounter}/1000 users - ${addressCounter} addresses - ${postCounter} posts - ${orderCounter} orders\n";
            $statements = '';
        }
    }

    if ($statements !== '') {
        $connection->exec($statements);
        echo "Created : ${userCounter}/1000 users - ${addressCounter} addresses - ${postCounter} posts - ${orderCounter} orders\n";
    }

    fclose($csvOrderHandler);
    indexOrdersIntoSearchEngines($engine, $redisClient);
}

function createDatabaseSchema(\Doctrine\DBAL\Connection $connection, array $schema)
{
    $databases = $connection->query('SHOW DATABASES;')->fetchAll(\PDO::FETCH_COLUMN);

    if (in_array('socialnetwork', $databases)) {
        $connection->exec('DROP DATABASE socialnetwork;');
        $databases = $connection->query('SHOW DATABASES;')->fetchAll(\PDO::FETCH_COLUMN);
    }

    foreach ($schema as $scheme) {
        list($database, $table) = explode('.', $scheme->getFullyQualifiedName());

        if (!in_array($database, $databases)) {
            $connection->exec('CREATE DATABASE ' . $database);
            $databases[] = $database;
        }

        $connection->exec('USE ' . $database);

        $tables = $connection->query('SHOW TABLES;')->fetchAll(\PDO::FETCH_COLUMN);

        if (in_array($table, $tables)) {
            // var_dump($connection->query('DESCRIBE ' . $table)->fetchAll());
            continue;
        }

        $statement = '';

        switch ($table) {
            case 'address':
                $statement = 'CREATE TABLE address (
                    id int PRIMARY KEY,
                    id_user int NOT NULL,
                    street varchar(255),
                    postcode varchar(20),
                    city varchar(100),
                    country varchar(100),
                    FOREIGN KEY (id_user)
                        REFERENCES user (id)
                        ON UPDATE RESTRICT ON DELETE CASCADE
                ) ENGINE=INNODB';
                break;

            case 'post':
                $statement = 'CREATE TABLE post (
                    id int PRIMARY KEY,
                    id_user int NOT NULL,
                    published_at DATE,
                    title varchar(255),
                    content mediumtext,
                    FOREIGN KEY (id_user)
                        REFERENCES user (id)
                        ON UPDATE RESTRICT ON DELETE CASCADE
                ) ENGINE=INNODB';

                break;

            case 'user':
                $statement = 'CREATE TABLE user (
                    id int PRIMARY KEY,
                    firstname varchar(50) NOT NULL,
                    lastname varchar(50) NOT NULL,
                    email varchar(50),
                    phone varchar(30),
                    registered_at DATE,
                    status int DEFAULT 0
                ) ENGINE=INNODB';
                break;
        }

        $connection->exec($statement);
    }
}

function indexOrdersIntoSearchEngines(QueryEngine $engine, PredisAdapter $redisClient)
{
    $orderIndex = (new \Ehann\RediSearch\Index($redisClient))->setIndexName('customers_orders');

    $orderIndex
        ->addTagField('order_id', true)
        ->addNumericField('price', true)
        ->addNumericField('vat', true)
        ->addTagField('currency', true)
        ->addTagField('credit_card_type', true)
        ->addTagField('credit_card_number', true)
        ->addTagField('customer_id', true)
        ->addTextField('customer_firstname')
        ->addTextField('customer_lastname')
        ->addTextField('customer_email')
        ->addTagField('products_ids', true, false, ',')
        ->addTagField('addresses_ids', true, false, ',')
        ->addTextField('addresses_countries');

    try {
        $orderIndex->drop();
    } catch (\Exception $e) {

    }

    $orderIndex->create();

    $model = Schema\Order::make('default', 'order')
        ->with(Schema\Product::make())
        ->with(Schema\User::make()
            ->with(Schema\Address::make())
        )
    ;

    $criteria = (new QueryCriteria())
        ->forCollection('order')
            ->addOption('filepath', __DIR__ . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'orders.csv')
            ->addOption('filepath', 'orders.csv')
        ;

    $pushed = ETL::extract($engine->scroll($model, $criteria, 5), 500, 500)
        ->transform(function($item) {
            return $item->toArray();
        })
        ->transform(function($item) {
            return [
                'order_id' => $item['id'],
                'price' => $item['price'],
                'vat' => $item['vat'],
                'currency' => $item['currency'],
                'credit_card_type' => $item['credit_card_type'],
                'credit_card_number' => $item['credit_card_number'],
                'customer_id' => $item['customer']['id'],
                'customer_firstname' => $item['customer']['firstname'],
                'customer_lastname' => $item['customer']['lastname'],
                'customer_email' => $item['customer']['email'],
                'products_ids' => implode(',', array_column($item['products'], '_id')),
                'addresses_ids' => implode(',', array_column($item['customer']['addresses'] ?? [], 'id')),
                'addresses_countries' => implode(',', array_column($item['customer']['addresses'] ?? [], 'country')),
            ];
        })
        ->load(function(iterable $bulk) use ($orderIndex) {
            foreach ($bulk as $document) {
                $orderIndex->add($document);
            }

            echo sprintf("[ETL] Redisearch - Indexing %d elements\n", count($bulk));
        })
        ->run();

    echo sprintf("[ETL] Indexed %d elements\n", $pushed);
}
