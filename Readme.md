# Presentation

Simple multi-database library to search content on different engines and aggregate those results into document-oriented structures.

# Drivers

* Doctrine (only MySQL and MariaDB are supported)
* Elasticsearch (Not tested)
* Redisearch (WIP)
* CsvReader (WIP)
* MongoDB (To do)
* Algolia (To do)
* Redis (To do)
* RssReader (To do)
* ScyllaDB (TBD)
* Cassandra (TBD)
* Solr (TBD)
* PostgreSQL (TBD)

# Filters

| Name   | Constant name                | Description  |
| ------ | ---------------------------- | ------------ |
| IN     | FILTER_IN                    |              |
| NOT IN | FILTER_NOT_IN                |              |
| GT     | FILTER_GREATER_THAN          |              |
| GTE    | FILTER_GREATER_THAN_EQUAL    |              |
| LT     | FILTER_LESS_THAN             |              |
| LTE    | FILTER_LESS_THAN_EQUAL       |              |
| MATCH  | FILTER_FULLTEXT_MATCH        |              |

Constants are defined by the interface **MKCG\Model\DBAL\FilterInterface**


Filters supported by driver
---------------------------

| Driver        | IN  | NOT IN | GT  | GTE | LT  | LTE | MATCH                           |
| ------------- | --- | ------ | --- | --- | --- | --- | ------------------------------- |
| Doctrine      | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%<value>%" |
| Elasticsearch | YES | YES    | YES | YES | YES | YES | YES                             |
| Redisearch    |     |        |     |     |     |     |                                 |
| CsvReader     | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%<value>%" |


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
