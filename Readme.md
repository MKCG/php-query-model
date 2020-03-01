# Presentation

Simple multi-database library to search content on different engines and aggregate those results into document-oriented structures.


# Drivers

| Type                | Name          | Description                                                             |
| ------------------- | ------------- | ----------------------------------------------------------------------- |
| Relational database | Doctrine      | Doctrine DBAL Adapter (MySQL, MariaDB are supported, other might not)   |
| Search engine       | Elasticsearch | Supports for Elasticsearch version 5+ (Work in progress)                |
| Search engine       | Redisearch    | Supports for Redisearch (Work in progress)                              |
| File reader         | CsvReader     |                                                                         |
| HTTP                | Http          | Interact with remote url (Work in progress)                             |
| HTTP                | HttpRobot     | Parse robots.txt from remote url (Work in progress)                     |
| RSS                 | RssReader     | Extract RSS from remote url                                             |
| Sitemap             | SitemapReader | Extract Sitemap urlset from remote url                                  |

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
| Redisearch        | YES        | WIP        | WIP      | WIP          | WIP   |
| MongoDB           | YES        | WIP        | WIP      | WIP          | WIP   |
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

| Driver        | IN  | NOT IN | GT  | GTE | LT  | LTE | MATCH                           | CUSTOM |
| ------------- | --- | ------ | --- | --- | --- | --- | ------------------------------- | ------ |
| Htp           | NO  | NO     | NO  | NO  | NO  | NO  | NO                              | WIP    |
| HttpRobot     | NO  | NO     | NO  | NO  | NO  | NO  | NO                              | NO     |
| Doctrine      | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"   | YES    |
| Elasticsearch | YES | YES    | YES | YES | YES | YES | YES                             | WIP    |
| MongoDB       | YES | YES    | YES | YES | YES | YES | YES                             | YES    |
| Redisearch    |     |        |     |     |     |     |                                 | WIP    |
| CsvReader     | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"   | YES    |
| RssReader     | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"   | YES    |
| SitemapReader | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"   | YES    |


## CUSTOM filter type


Custom filters can be applied by providing a `callable` to the `QueryCriteria` instance :

```
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

| Driver        | First argument         | Second argument                   |
| ------------- | ---------------------- | --------------------------------- |
| Doctrine      | \MKCG\Model\DBAL\Query | \Doctrine\DBAL\Query\QueryBuilder |
| CsvReader     | \MKCG\Model\DBAL\Query | `array` representing a raw item   |
| RssReader     | \MKCG\Model\DBAL\Query | `array` representing a raw item   |
| SitemapReader | \MKCG\Model\DBAL\Query | `array` representing a raw item   |

# Example

A fully functionnal example is located in **examples/SocialNetwork**

From : ./examples

```
docker-compose up --build -d
docker exec -it php_query_model sh -c "cd /home/php-query-model/examples && composer install"
docker exec -it php_query_model sh -c "php /home/php-query-model/examples/index.php"
```

# Roadmap

## Expected features

Work In progress
----------------

| Feature              | Description                                                 |
| -------------------- | ----------------------------------------------------------- |
| Elasticsearch Driver |                                                             |
| Redisearch Driver    |                                                             |
| Aggregations         |                                                             |
| Content type         | Define the type of each schema property                     |
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
* MongoDB
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
