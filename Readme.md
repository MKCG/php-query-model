# Presentation

Simple multi-database library to search content on different engines and aggregate those results into document-oriented structures.

# Drivers

* Doctrine (only MySQL and MariaDB are supported)
* CsvReader
* RssReader (Partially implemented)
* SitemapReader (Partially implemented)
* Elasticsearch (Not tested)
* Redisearch (WIP)
* MongoDB (To do)
* Algolia (To do)
* Redis (To do)
* ScyllaDB (TBD)
* Cassandra (TBD)
* Solr (TBD)
* PostgreSQL (TBD)


Features supported by driver
----------------------------

| Driver            | Scrollable | Filterable | Sortable | Aggregatable | Count |
| ----------------- | ---------- | ---------- | -------- | ------------ | ----- |
| Doctrine          | YES        | YES        | YES      | WIP          | YES   |
| CsvReader         | YES        | YES        | NO       | YES          | YES   |
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

# Filters

| Name   | Constant name                | Description                                      |
| ------ | ---------------------------- | ------------------------------------------------ |
| IN     | FILTER_IN                    |                                                  |
| NOT IN | FILTER_NOT_IN                |                                                  |
| GT     | FILTER_GREATER_THAN          |                                                  |
| GTE    | FILTER_GREATER_THAN_EQUAL    |                                                  |
| LT     | FILTER_LESS_THAN             |                                                  |
| LTE    | FILTER_LESS_THAN_EQUAL       |                                                  |
| MATCH  | FILTER_FULLTEXT_MATCH        |                                                  |
| CUSTOM | FILTER_CUSTOM                | Allow to use a callable to apply complex filters |

Constants are defined by the interface **MKCG\Model\DBAL\FilterInterface**


Filters supported by driver
---------------------------

| Driver        | IN  | NOT IN | GT  | GTE | LT  | LTE | MATCH                           | CUSTOM |
| ------------- | --- | ------ | --- | --- | --- | --- | ------------------------------- | ------ |
| Doctrine      | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%value%"   | WIP    |
| Elasticsearch | YES | YES    | YES | YES | YES | YES | YES                             | WIP    |
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

# Contribution

Feel free to open a merge request for any suggestion or to contribute to this project.
