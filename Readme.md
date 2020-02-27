Presentation
------------
Simple multi-database library to search content on different engines and aggregate those results into document-oriented structures.

Drivers
-------
* Doctrine (only MySQL and MariaDB are supported)
* Elasticsearch (Not tested)
* Redisearch (WIP)
* MongoDB (To do)
* Algolia (To do)
* Redis (To do)
* ScyllaDB (TBD)
* Cassandra (TBD)
* Solr (TBD)
* PostgreSQL (TBD)

Example
-------

From : ./examples

```
docker-compose up --build -d
docker exec -it php_query_model sh -c "php /home/php-query-model/examples/index.php"
```

Contribution
------------
Feel free to open a merge request for any suggestion or to contribute to this project.
