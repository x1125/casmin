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

![Screenshot1](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot1.png)
![Screenshot2](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot2.png)
![Screenshot3](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot3.png)
![Screenshot4](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot4.png)
![Screenshot5](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot5.png)
![Screenshot6](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot6.png)
![Screenshot7](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot7.png)
![Screenshot8](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot8.png)
![Screenshot9](https://raw.githubusercontent.com/x1125/casmin/master/screenshots/screenshot9.png)