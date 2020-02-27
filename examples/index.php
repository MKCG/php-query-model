<?php

require __DIR__ . '/vendor/autoload.php';

use MKCG\Examples\SocialNetwork\Schema;
use MKCG\Model\DBAL\FilterInterface;
use MKCG\Model\DBAL\QueryCriteria;
use MKCG\Model\DBAL\Drivers;

$redisClient = new \Predis\Client(['scheme' => 'tcp', 'host' => 'redisearch', 'port' => 6379]);
$httpClient = new \Guzzle\Http\Client('http://elasticsearch:9200/');
$sqlConnection = \Doctrine\DBAL\DriverManager::getConnection([
    'user' => 'root',
    'password' => 'root',
    'host' => 'mysql',
    'driver' => 'pdo_mysql',
]);

// createFakeData($sqlConnection);


$engine = new \MKCG\Model\DBAL\QueryEngine('mysql');
$engine->registerDriver(new Drivers\Doctrine($sqlConnection), 'mysql');

$model = Schema\User::make('default', 'user')
    ->with(Schema\Address::make())
    ->with(Schema\Post::make());

$criteria = (new QueryCriteria())
    ->forCollection('user')
        ->addFilter('status', FilterInterface::FILTER_IN, [ 2 , 3 , 5 , 7 ])
        ->addFilter('registered_at', FilterInterface::FILTER_GREATER_THAN_EQUAL, '2000-01-01')
        ->addSort('firstname', 'ASC')
        ->addSort('lastname', 'ASC')
        ->setLimit(10)
    ->forCollection('addresses')
        ->setLimitByParent(2)
    ->forCollection('posts')
        ->addFilter('title', FilterInterface::FILTER_FULLTEXT_MATCH, 'ab')
;

// $users = $engine->query($model, $criteria);

// echo json_encode($users->getContent(), JSON_PRETTY_PRINT) . "\n";
// echo "\nFound : " . $users->getCount() . " users\n";

$iterator = $engine->scroll($model, $criteria);

foreach ($iterator as $i => $user) {
    var_dump($user);
}

function createFakeData(\Doctrine\DBAL\Connection $connection)
{
    $connection->exec('DROP DATABASE socialnetwork;');

    createDatabaseSchema($connection, [ new Schema\User(), new Schema\Address(), new Schema\Post() ]);

    $faker = \Faker\Factory::create();

    $userCounter = 0;
    $addressCounter = 0;
    $postCounter = 0;

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
            echo "Created : ${userCounter} users - ${addressCounter} addresses - ${postCounter} posts\n";
            $statements = '';
        }
    }

    if ($statements !== '') {
        $connection->exec($statements);
        echo "Created : ${userCounter} users - ${addressCounter} addresses - ${postCounter} posts\n";
    }
}

function createDatabaseSchema(\Doctrine\DBAL\Connection $connection, array $schema)
{
    $databases = $connection->query('SHOW DATABASES;')->fetchAll(\PDO::FETCH_COLUMN);

    foreach ($schema as $scheme) {
        list($database, $table) = explode('.', $scheme->getFullyQualifiedTableName());

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
