---
layout: default
---

<!-- TODO: instead of linking to GitHub, link to the generated API docs -->

# Getting Started


## Install

Using [Composer](https://getcomposer.org), the installation is trivial:
```
composer require ondrej-bouda/ivory
```
PHP ≥ 7.2 is required with several standard extensions<sup>[1](#footnote1)</sup>, checked by Composer.
PostgreSQL ≥ 9.4 is officially supported, although older PostgreSQL versions might work just fine.
    
During installation, a [PSR-6](http://www.php-fig.org/psr/psr-6/)-compliant cache is suggested. We'll get back to it
later.


## Connect

First, a database connection must be set up:
```php
<?php
$connParams = ConnectionParameters::create([
    ConnectionParameters::HOST => 'localhost',
    ConnectionParameters::USER => 'ivory',
    ConnectionParameters::PASSWORD => '***',
    ConnectionParameters::DBNAME => 'ivory_test',
]);
$conn = Ivory::setupNewConnection($connParams);
```
The connection is both returned to `$conn` and registered in a global connection storage so that it is available from
anywhere. Since this is the first registered connection, it automatically becomes the default one.

Once the connection is defined, we may start connecting to the database:
```php
<?php
$conn->connect();
```
The method returns immediately while the connection is being established in the background. In the meantime, the
application may do jobs not requiring database, such as routing or input processing.


## Query

```php
<?php
$conn = Ivory::getConnection();
$relation = $conn->query(
    'SELECT * FROM album WHERE EXTRACT(YEAR FROM released) >= %i ORDER BY released',
    2000
);
foreach ($relation as $tuple) {
    echo $tuple->name . ', released ' . $tuple->released->format('n/j/Y') . PHP_EOL;
}
```
First, the default connection is grabbed. The `query()` method is given an [SQL pattern](features.md#sql-patterns) with
parameters. It waits for the connection to be ready, then sends the SQL query to the database and returns the resulting
relation. In the relation, a `text` column _name_ and a `date` column _released_ are present, which are available as
values of PHP type `string` and `\Ivory\Value\Date`, respectively.

Out of the box, Ivory supports all
[standard data types](https://www.postgresql.org/docs/11/datatype.html#DATATYPE-TABLE) shipped with PostgreSQL and all
type constructors -- arrays, composites, enums, ranges, and domains.
```php
<?php
$ranges = $conn->querySingleValue(<<<'SQL'
    SELECT ARRAY[
               daterange('2015-05-19', '2015-12-01'),
               daterange('2015-12-01', '2017-02-19'),
               daterange('2017-02-19', NULL)
           ]
SQL
);
echo $ranges[2]->getLower()->format('n/j/Y'); // prints "12/1/2015"
```
Note that Ivory keeps the array indices when converting to PHP arrays (and vice versa), i.e., `$ranges` is a one-based
array. That may be suppressed by configuring the array type converter, though.

**There's a lot more on types** -- see the documentation chapter [Data Types](documentation.md#data-types).


## Command

Statements which do not yield data sets, such as `INSERT` or `UPDATE`, are executed using the `command()` method, which
only returns the summary effect of the statement:
```php
<?php
$result = $conn->command(
    'INSERT INTO album (name, released) VALUES (%s, %date)',
    'Hardwired...To Self-Destruct',
    \Ivory\Value\Date::fromParts(2016, 11, 18)
);
echo 'Inserted ' . $result->getAffectedRows() . ' rows' . PHP_EOL; // prints "Inserted 1 rows"
```
Unlike other database layers, which use `query()` for everything, **Ivory strictly distinguishes between `query()` and
`command()`** as they have different effects and return a different class of result.


## Set Up Cache

Using a cache is vital for Ivory to prevent introspecting the data types on each request and parsing the same SQL
patterns over and over again. For basic usage, a filesystem cache will do the job. For production,
[Memcached](http://php.net/manual/en/book.memcached.php) or similar shared memory cache is more appropriate.

The only step is to plug in a [PSR-6](http://www.php-fig.org/psr/psr-6/)-compliant cache before connecting to the
database. For example, install:
```
composer require cache/filesystem-adapter
```
and use:
```php
<?php
$fsAdapter = new \League\Flysystem\Adapter\Local(sys_get_temp_dir());
$fs = new \League\Flysystem\Filesystem($fsAdapter);
$cachePool = new \Cache\Adapter\Filesystem\FilesystemCachePool($fs);
Ivory::setDefaultCacheImpl($cachePool);
```


## Putting It All Together

All the pieces, including the test database DDL and data, follow for you to play with:
```
composer require ondrej-bouda/ivory cache/filesystem-adapter
```
```php
<?php
use Ivory\Connection\ConnectionParameters;
use Ivory\Ivory;

require __DIR__ . '/vendor/autoload.php';

// Setup cache
$fsAdapter = new \League\Flysystem\Adapter\Local(sys_get_temp_dir());
$fs = new \League\Flysystem\Filesystem($fsAdapter);
$cachePool = new \Cache\Adapter\Filesystem\FilesystemCachePool($fs);
Ivory::setDefaultCacheImpl($cachePool);

// Setup connection
$connParams = ConnectionParameters::create([
    ConnectionParameters::HOST => 'localhost',
    ConnectionParameters::USER => 'ivory',
    ConnectionParameters::PASSWORD => '***',
    ConnectionParameters::DBNAME => 'ivory_test',
]);
$conn = Ivory::setupNewConnection($connParams);
$conn->connect();

// Sample database
$conn->runScript(<<<'SQL'
    DROP TABLE IF EXISTS album;
    CREATE TABLE album (
        id BIGSERIAL PRIMARY KEY,
        name TEXT NOT NULL,
        released DATE
    );
    INSERT INTO album (name, released) VALUES
        ('The Piano Guys', '2012-10-02'),
        ('Black Album', '1991-08-12'),
        ('S & M', '1999-11-23'),
        ('Live One', '2005-01-01'),
        ('Meeting Point', '2014-10-27');
SQL
);

// Query
$relation = $conn->query(
    'SELECT * FROM album WHERE EXTRACT(YEAR FROM released) >= %i ORDER BY released',
    2000
);
foreach ($relation as $tuple) {
    echo $tuple->name . ', released ' . $tuple->released->format('n/j/Y') . PHP_EOL;
}

$ranges = $conn->querySingleValue(<<<'SQL'
    SELECT ARRAY[
               daterange('2015-05-19', '2015-12-01'),
               daterange('2015-12-01', '2017-02-19'),
               daterange('2017-02-19', NULL)
           ]
SQL
);
echo $ranges[2]->getLower()->format('n/j/Y') . PHP_EOL; // prints "12/1/2015"

// Command
$result = $conn->command(
    'INSERT INTO album (name, released) VALUES (%s, %date)',
    'Hardwired...To Self-Destruct',
    \Ivory\Value\Date::fromParts(2016, 11, 18)
);
echo 'Inserted ' . $result->getAffectedRows() . ' rows' . PHP_EOL; // prints "Inserted 1 rows"

$cnt = $conn->querySingleValue('SELECT COUNT(*) FROM album');
echo 'Album count: ' . $cnt . PHP_EOL; // prints "Album count: 6"
```


## Further Steps

First, reading the [Features](features.md) page is recommended to get the idea about what Ivory offers.

To dive more into details, you can study the [documentation](documentation.md). There is a more enjoyable way, however
-- a set of unit tests especially written to demonstrate specifics and capabilities of Ivory. Come and play with the
unit test classes within [`Ivory\Showcase`](https://github.com/ondrej-bouda/ivory/tree/master/test/unit/Ivory/Showcase)!


___

<small>
<a name="footnote1"><sup>1</sup></a>
Currently, Ivory needs the following PHP extensions, all of which are a part of the standard PHP distribution:
`bcmath`, `dom`, `iconv`, `json`, `mbstring`, `pgsql`, `simplexml`, and `xmlreader`.
<br>
</small>
