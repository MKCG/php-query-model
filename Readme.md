# Presentation

Simple multi-database library to search content on different engines and aggregate those results into document-oriented structures.

The library define the class `MKCG\Model\DBAL\QueryEngine` to build documents using different `Drivers`.

It also defines a simple `ETL` to be able to easily and efficiently synchronize content between different datasources.


# Engine API

The `QueryEngine` API define two methods : `query()` and `scroll()`.

Each one can fetch and build documents using the provided `\MKCG\Model\Model` with the appropriate `\MKCG\Model\DBAL\QueryCriteria`.
However, the `scroll()` method return a `\Generator` and internally performs multiple batches to efficiently scroll big collections.

Examples
--------

```php
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

$users = $engine->query($model, $criteria);

echo json_encode($users->getContent(), JSON_PRETTY_PRINT) . "\n";
echo "\nFound : " . $users->getCount() . " users\n";


$iterator = $engine->scroll($model, $criteria);

foreach ($iterator as $user) {
    echo json_encode($user, JSON_PRETTY_PRINT) . "\n";
}

```

## Drivers definition

Each `Driver` is responsible to perform queries on a single `datasource` (database, HTTP API, local files, ...) and must :
* implements the `MKCG\Model\DBAL\Drivers\DriverInterface`
* registered inside the `QueryEngine`


Example
-------

```php
use MKCG\Model\DBAL\QueryEngine;
use MKCG\Model\DBAL\Drivers;

$mongoClient = new MongoDB\Client('mongodb://root:password@mongodb');

$redisClient = new \Predis\Client([
    'scheme' => 'tcp',
    'host' => 'redisearch',
    'port' => 6379
]);

$sqlConnection = \Doctrine\DBAL\DriverManager::getConnection([
    'user' => 'root',
    'password' => 'root',
    'host' => 'mysql',
    'driver' => 'pdo_mysql',
]);

$engine = (new QueryEngine('mysql'))
    ->registerDriver(new Drivers\Doctrine($sqlConnection), 'mysql')
    ->registerDriver(new Drivers\CsvReader($fixturePath), 'csv')
    ->registerDriver(new Drivers\RssReader(new Adapters\Guzzle), 'rss')
    ->registerDriver(new Drivers\SitemapReader(new Adapters\Guzzle), 'sitemap')
    ->registerDriver(new Drivers\Http(new Adapters\Guzzle), 'http')
    ->registerDriver(new Drivers\HttpRobot(new Adapters\Guzzle), 'http_robot')
    ->registerDriver(new Drivers\MongoDB($mongoClient), 'mongodb')
```

## Runtime behaviors


# Drivers

| Type                       | Name          | Description                                                             |
| -------------------------- | ------------- | ----------------------------------------------------------------------- |
| Document-oriented database | MongoDB       | Driver for MongoDB 3.6+                                                 |
| Relational database        | Doctrine      | Doctrine DBAL Adapter (MySQL, MariaDB are supported, other might not)   |
| Search engine              | Elasticsearch | Driver for Elasticsearch 5+ (Work in progress)                          |
| Search engine              | Redisearch    | Driver for redisearch                                                   |
| File reader                | CsvReader     |                                                                         |
| HTTP                       | Http          | Interact with remote url (Work in progress)                             |
| HTTP                       | HttpRobot     | Parse robots.txt from remote url (Work in progress)                     |
| RSS                        | RssReader     | Extract RSS from remote url                                             |
| Sitemap                    | SitemapReader | Extract Sitemap urlset from remote url                                  |

Features supported by driver
----------------------------

| Driver            | Scrollable | Filterable | Sortable | Aggregatable | Count |
| ----------------- | ---------- | ---------- | -------- | ------------ | ----- |
| Doctrine          | YES        | YES        | YES      | WIP          | YES   |
| CsvReader         | YES        | YES        | NO       | YES          | YES   |
| Http              | YES        | YES        | NO       | NO           | NO    |
| HttpRobot         | YES        | NO         | NO       | NO           | NO    |
| RssReader         | YES        | YES        | NO       | NO           | NO    |
| SitemapReader     | YES        | YES        | NO       | NO           | NO    |
| Elasticsearch     | YES        | YES        | YES      | WIP          | WIP   |
| Redisearch        | YES        | YES        | YES      | YES          | YES   |
| MongoDB           | YES        | YES        | YES      | YES          | YES   |
| Algolia           | YES        | WIP        | WIP      | NO           | WIP   |
| Redis             | YES        | WIP        | NO       | NO           | NO    |
| ScyllaDB          | YES        | WIP        | WIP      |              | WIP   |
| Cassandra         | YES        | WIP        | WIP      |              | WIP   |
| Solr              | YES        | WIP        | WIP      | WIP          | WIP   |
| PostgreSQL        | YES        | WIP        | WIP      | WIP          | WIP   |


Query criteria options
----------------------

HTTP-based drivers :
* HTTP
* HttpRobot
* RssReader
* SitemapReader
* Elasticsearch

Result-based filterable drivers :
* CsvReader
* RssReader
* SitemapReader


| Option            | Drivers                                  | Description                                                                   |
| ----------------- | ---------------------------------------- | ----------------------------------------------------------------------------- |
| case_sensitive    | MongoDB, Result-based filterable drivers | Perform case sensitive `FILTER_FULLTEXT_MATCH` search , default : `false`     |
| filepath          | CsvReader                                | Absolute or relative filepath of the CSV                                      |
| json_formatter    | HTTP                                     | Format JSON response body using a callback                                    |
| multiple_requests | none , used by the QueryEngine           | Disable sub-requests batching when including sub-models                       |
| url               | HTTP-based drivers                       | Define the URL to use to query                                                |
| url_generator     | HTTP-based drivers                       | Use a callback to generate the URL to use based on the Query                  |
| max_query_time    | HTTP-based drivers , MongoDB             | Max query time in milliseconds , default : `5000` (5 seconds)                 |
| allow_partial     | MongoDB , Elasticsearch                  | Allow partial results to be returned , default : `false`                      |
| readPreference    | MongoDB                                  | https://docs.mongodb.com/manual/core/read-preference/index.html               |
| readConcern       | MongoDB                                  | https://docs.mongodb.com/manual/reference/read-concern/index.html             |
| batchSize         | MongoDB                                  | https://docs.mongodb.com/manual/reference/method/cursor.batchSize/index.html  |
| diacriticSensitive| MongoDB                                  | https://docs.mongodb.com/manual/reference/operator/query/text/index.html      |

When both `url_generator` and `url` are provided, then only `url_generator` is used.

# Filters

| Name   | Constant name                | Description                                      |
| ------ | ---------------------------- | ------------------------------------------------ |
| IN     | FILTER_IN                    |                                                  |
| NOT IN | FILTER_NOT_IN                |                                                  |
| GT     | FILTER_GREATER_THAN          |                                                  |
| GTE    | FILTER_GREATER_THAN_EQUAL    |                                                  |
| LT     | FILTER_LESS_THAN             |                                                  |
| LTE    | FILTER_LESS_THAN_EQUAL       |                                                  |
| MATCH  | FILTER_FULLTEXT_MATCH        | Text search                                      |
| CUSTOM | FILTER_CUSTOM                | Allow to use a callable to apply complex filters |

Constants are defined by the interface **MKCG\Model\DBAL\FilterInterface**


Filters supported by driver
---------------------------

| Driver        | IN  | NOT IN | GT  | GTE | LT  | LTE | MATCH                                      | CUSTOM |
| ------------- | --- | ------ | --- | --- | --- | --- | ------------------------------------------ | ------ |
| Http          | NO  | NO     | NO  | NO  | NO  | NO  | NO                                         | NO     |
| HttpRobot     | NO  | NO     | NO  | NO  | NO  | NO  | NO                                         | NO     |
| Doctrine      | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"              | YES    |
| Elasticsearch | YES | YES    | YES | YES | YES | YES | YES , using elasticsearch `match` filter   | WIP    |
| MongoDB       | YES | YES    | YES | YES | YES | YES | YES , using mongodb `$text` operator       | YES    |
| Redisearch    | YES | YES    | YES | YES | YES | YES | YES , using redisearch `search` syntax     | NO     |
| CsvReader     | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"              | YES    |
| RssReader     | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"              | YES    |
| SitemapReader | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"              | YES    |


## CUSTOM filter type


Custom filters can be applied by providing a `callable` to the `QueryCriteria` instance :

```php
(new QueryCriteria())
    ->forCollection('order')
        ->addCallableFilter(function(Query $query, ...$arguments) {
            // do something
        })
```

The first argument of the `callable` SHOULD always be the `Query` instance.
Other arguments might change depending on the driver.


Some `Driver` apply filters on fetched results and expect a `false` return value when the filter does not match. Internaly they apply a `array_filter` on each fetched result before :
* CsvReader
* RssReader
* SitemapReader


`callable` arguments by Driver
------------------------------

| Driver        | First argument         | Second argument                                                                           |
| ------------- | ---------------------- | ----------------------------------------------------------------------------------------- |
| Doctrine      | \MKCG\Model\DBAL\Query | \Doctrine\DBAL\Query\QueryBuilder                                                         |
| CsvReader     | \MKCG\Model\DBAL\Query | `array` representing a raw item                                                           |
| RssReader     | \MKCG\Model\DBAL\Query | `array` representing a raw item                                                           |
| SitemapReader | \MKCG\Model\DBAL\Query | `array` representing a raw item                                                           |
| MongoDB       | \MKCG\Model\DBAL\Quert | `array` representing the filters passed as first argument of `\MongoDB\Collection::find()`|

# ETL

A deadly simple ETL is defined as a single class `\MKCG\Model\ETL`.
It can be used in combination with the QueryEngine `scroll` API to transform then push content to different loaders;

Example
-------

```php
function pipelineEtl(QueryEngine $engine)
{
    $model = Schema\Product::make('default', 'products');
    $criteria = (new QueryCriteria())
        ->forCollection('products')
            ->addFilter('sku.color', FilterInterface::FILTER_IN, ['aqua', 'purple'])
        ;

    $iterator = $engine->scroll($model, $criteria, 100);

    $pushed = ETL::extract($engine->scroll($model, $criteria, 100), 1000, 500)
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
```

# Aggregations

| NAME              | Description                                                                                   |
| ----------------- | --------------------------------------------------------------------------------------------- |
| TERMS             | Number of distinct elements by field , with the field filters considered by the aggregation   |
| FACET             | Number of distinct elements by field , with the field filters excluded by the aggregation     |
| AVERAGE           | Average value of a numeric field                                                              |
| MIN               | Min value of a field                                                                          |
| MAX               | Max value of a field                                                                          |
| QUANTILE          | Quantile value of a field                                                                     |


Aggregrations supported
-----------------------

| Driver     | TERMS     | FACET    | AVERAGE   | MIN     | MAX     | QUANTILE  |
| ---------- | --------- | -------- | --------- | ------- | ------- | --------- |
| Doctrine   | NO        | NO       | YES       | YES     | YES     | YES       |
| CsvReader  | NO        | NO       | YES       | YES     | YES     | NO        |
| Redisearch | YES       | YES      | YES       | YES     | YES     | YES       |
| MongoDB    | YES       | YES      | YES       | YES     | YES     | YES       |


# Test and examples

No tests are provided although some will be made using `Behat` for the release of the version 1.0.0.
However a fully functionnal example is provided in `examples/` and build documents using different kinds of `Drivers`

From : ./examples

```bash
docker-compose up --build -d
docker exec -it php_query_model sh -c "cd /home/php-query-model/examples && composer install"
docker exec -it php_query_model sh -c "php /home/php-query-model/examples/index.php"
```


By default this will run only two functions (located in `index.php`)

```php
pipelineEtl($engine);
searchOrder($engine);
// searchProducts($engine);
// searchGithubRobot($engine);
// searchSitemaps($engine);
// searchPackages($engine);
// searchUsers($engine);
// searchHackerNews($engine);
```

The `pipelineEtl` use the Engine `scroll` API to iterates a list of `Product` stored in MongoDB and apply different `transformations`before pushing content with three `loaders` using the `ETL` component.


The `searchOrder` use the Engine `scroll` API to :
- scan a `CSV` file containing ecommerce `Order`
    - then inject their corresponding `Product` stored on `MongoDB`
    - then inject their correspondng customers stored as `User` into `MySQL`
        - with their first two defined `Address` also stored in `Mysql`
        - and all their `Post` stored in `Mysql`


You might want to uncomment the other search functions to execute HTTP queries and fetch :
- https://github.com/robots.txt with `searchGithubRobot`
- https://news.ycombinator.com/ top stories with `searchHackerNews`
- https://packagist.org/feeds/packages.rss RSS feed with `searchPackages`
- https://www.sitemaps.org/sitemap.xml Sitemap with `searchSitemaps`

# Roadmap

## Expected features

Work In progress
----------------

| Feature              | Description                                                 |
| -------------------- | ----------------------------------------------------------- |
| Elasticsearch Driver |                                                             |
| Aggregations         | Implemented for Redisearch and MongoDB                      |
| Callable validation  | Validate callable arguments using Reflection and PHP tokens |

Backlog
-------

| Feature                   | Description                                                                        |
| ------------------------- | ---------------------------------------------------------------------------------- |
| Async HTTP requests       | Perform non-blocking HTTP requests                                                 |
| Lazy requests             | Only perform requests when the content is manipulated                              |
| Cacheable requests        | Cache results and detect what to invalidate using surrogate keys                   |
| Content synchronizer      | Use streamed eventlog to synchronize content between each datasource               |
| Error handling strategies | Allow to apply different strategy in case of a failure : crash, retry, fallback... |
| Generate schema classes   | Generate Schema classes by analyzing each database schema                          |
| Content lifecycle         | Allow to create / update / delete content                                          |


Drivers "nice to have"
----------------------

Database Drivers
================
* Algolia
* ArangoDB
* Cassandra
* \Illuminate\Eloquent (library used by Laravel)
* Neo4J
* PostgreSQL
* Redis
* ScyllaDB
* Solr

Streaming
=========
* Kafka
* MySQL binlog
* RabbitMQ

Storage
=======
* AWS S3
* File system
* OpenIO

Infrastructure
==============
* AWS
* OVH

Service
=======
* Cloudinary
* Sendinblue

Social Network
==============
* Facebook
* LinkedIn
* Twitter

# Contribution

Feel free to open a merge request for any suggestion or to contribute to this project.
