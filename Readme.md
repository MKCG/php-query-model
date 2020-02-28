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

| Driver            | Scrollable | Filterable | Sortable | Aggregatable |
| ----------------- | ---------- | ---------- | -------- | ------------ |
| Doctrine          | YES        | YES        | YES      | WIP          |
| CsvReader         | YES        | YES        | NO       | YES          |
| RssReader         | YES        | WIP        | WIP      | NO           |
| SitemapReader     | YES        | WIP        | WIP      | NO           |
| Elasticsearch     | YES        | YES        | YES      | WIP          |
| Redisearch        | YES        | WIP        | WIP      | WIP          |
| MongoDB           | YES        | WIP        | WIP      | WIP          |
| Algolia           | YES        | WIP        | WIP      | NO           |
| Redis             | YES        | WIP        | YES      | NO           |
| ScyllaDB          | YES        | WIP        | WIP      |              |
| Cassandra         | YES        | WIP        | WIP      |              |
| Solr              | YES        | WIP        | WIP      | WIP          |
| PostgreSQL        | YES        | WIP        | WIP      | WIP          |

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

| Driver        | IN  | NOT IN | GT  | GTE | LT  | LTE | MATCH                           |
| ------------- | --- | ------ | --- | --- | --- | --- | ------------------------------- |
| Doctrine      | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%<value>%" |
| Elasticsearch | YES | YES    | YES | YES | YES | YES | YES                             |
| Redisearch    |     |        |     |     |     |     |                                 |
| CsvReader     | YES | YES    | YES | YES | YES | YES | Interpreted as LIKE "%<value>%" |
| RssReader     | WIP | WIP    | WIP | WIP | WIP | WIP | WIP                             |
| SitemapReader | WIP | WIP    | WIP | WIP | WIP | WIP | WIP                             |


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
