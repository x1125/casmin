CasMin
======

A web interface for Cassandra.

It uses the cqlsh binary to provide the latest versions.

Features
--------

* Support latest CQL version
* Create and delete keyspaces and tables with properties
* Add and remove columns
* Show table contents (limited results)
* Execute plain queries

Supported versions
------------------

Cassandra | CQL Version | Supported
----------| ------------| ---------
1.2       | 3.0         | no
2.0, 2.1  | 3.1         | yes
2.2, 3.0, 3.x | 3.3     | yes

Installation
------------

You need [composer](https://getcomposer.org/) to download all required libraries.


```
composer install
```

The path of the vhost should point to the casmin/web directory.

Then edit the app/config/parameter.yml and add the paths to the cqlsh binaries.

```
    ...
    cqlsh:
        '3.1': '/opt/apache-cassandra-2.2.6/bin/cqlsh'
        '3.3': '/opt/apache-cassandra-3.7/bin/cqlsh'
```
(You just need to add the one you're using; they will be required on first access)

After changes on configurations, you'll have to clear the cache.

```
php bin/console cache:clear --env=prod
```

Screenshots
-----------

![Screenshot1](https://github.com/x1125/casmin/screenshots/screenshot1.png)
![Screenshot2](https://github.com/x1125/casmin/screenshots/screenshot2.png)
![Screenshot3](https://github.com/x1125/casmin/screenshots/screenshot3.png)
![Screenshot4](https://github.com/x1125/casmin/screenshots/screenshot4.png)
![Screenshot5](https://github.com/x1125/casmin/screenshots/screenshot5.png)
![Screenshot6](https://github.com/x1125/casmin/screenshots/screenshot6.png)
![Screenshot7](https://github.com/x1125/casmin/screenshots/screenshot7.png)
![Screenshot8](https://github.com/x1125/casmin/screenshots/screenshot8.png)
![Screenshot9](https://github.com/x1125/casmin/screenshots/screenshot9.png)