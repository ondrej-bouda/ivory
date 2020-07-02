---
layout: default
---

# Features
{:.no_toc}

An overview of Ivory features is split in two sections: the already implemented ones, and those which are planned for
the future. For more detailed description with examples, see the [Documentation](documentation.md).

* TOC
{:toc}


## Implemented Features

### Complete Data Type Support
* All data types delivered with the standard PostgreSQL installation are supported.
* The PHP counterparts represent their PostgreSQL data types closely and precisely, capable of holding all possible
  values as defined by PostgreSQL (infinity dates and times, dates having years beyond 4 digits, or precise `decimal`
  values, to name a few).
* All PostgreSQL type constructors are supported:
  * Enumeration types are recognized automatically.
  * Composite types are handled automatically, too, provided their attributes are of known types.
  * Range types are supported as well. Depending on the range subtype, a little effort might be needed to specify the
    behaviour for a new custom range type (discrete step definition). Standard PostgreSQL ranges are supported out of
    the box.
* Data type introspector automatically recognizes all custom types. Only those really used throughout the actual
  requests are loaded in memory.
* Custom base types may easily be plugged in. Just a single class has to be implemented and registered with Ivory.
* Arrays of any types are fully supported.
  * Multidimensional arrays are supported.
  * The optional array [subscript ranges](https://www.postgresql.org/docs/current/arrays.html#ARRAYS-IO) are supported,
    too.
  * The element delimiter is inferred automatically from the element type.
  * Arrays over custom types are handled automatically, with no extra effort.
* Data types are recognized by their _fully-qualified_ name. Two same-named types in different schemas are distinguished
  correctly.

### SQL Patterns
* A simple language over the SQL itself, useful for providing arguments to queries or commands in an injection-safe
  manner.
```php
<?php
$rel = $conn->query('SELECT * FROM person WHERE lastname = %s', 'Doe');
```
* Placeholders may specify the data type and the argument name.
  * The type may refer to any data type defined on the database, including its schema qualification.
  * Names are optional -- if omitted, the argument is a positional one.
  * Types are optional, too -- if omitted, the type is inferred automatically from the provided value.
* Even relations (table data) may be provided as arguments to queries.
```php
<?php
$vals = SqlRelationDefinition::fromFragments(
    'VALUES (%, %),', 4, Date::fromParts(2017, 2, 25),
    '       (%, %)', 7, null
);
$rel = $conn->query(
    'SELECT * FROM (%rel) AS t (id, creat)',
    $vals
);
```
* A special placeholder escapes the argument to be used as a `LIKE` operand.
```php
<?php
$match = $conn->querySingleValue("SELECT 'foobar' LIKE %_like_", 'oo'); // TRUE
```
* See the [documentation](documentation.md#sql-patterns) for more details.

### Transaction Control
* Transaction control is encapsulated in an object-oriented API. Exceptions thrown within transactions may be
  handled easily.
```php
<?php
$tx = $conn->startTransaction();
try {
    $conn->command('...');
    $tx->commit();
} finally {
    // if the command raised an exception, the transaction is rolled back:
    $tx->rollbackIfOpen();
}
```
* In the typical case when a transaction is performed within a function or method, **automatic transaction handles** may
  come in handy. Instead of writing the explicit boilerplate to rollback the transaction upon an exception, the
  `$tx->rollbackIfOpen()` gets called automatically when destructed. As a result, the code is simpler and foolproof.
```php
<?php
function () use ($conn)
{
    $tx = $conn->startAutoTransaction();
    // do stuff, exceptions expected
    $conn->command('INSERT INTO usr (username) VALUES (%s)', 'admin');
    $conn->command(
        "INSERT INTO usrrole (usr_id, role_id)
         SELECT currval(REGCLASS 'usr_id_seq'), id
         FROM role"
    );
    $tx->commit(); // if the program does not reach this statement due to an exception (perhaps due
                   // to a unique constraint fail), the transaction gets rolled back
}
```
* Prepared transactions (a.k.a. two-phase commits) are supported by an API, too.

```php
<?php
$tx = $conn->startTransaction();
$conn->command('...');
$txName = $tx->prepareTransaction();
// ...
$conn->commitPreparedTransaction($txName); // $conn is not necessarily the same connection
// or
$conn->rollbackPreparedTransaction($txName);

$conn->listPreparedTransactions(); // lists all prepared transactions
```


### Session Control
* Type-safe encapsulation of the database session configuration (e.g., statement timeout or search path).
{% highlight php %}
<?php
$cfg = $conn->getTxConfig();
$cfg->setForSession(ConfigParam::STATEMENT_TIMEOUT, Quantity::fromValue(1, Quantity::MINUTE));
$cfg->setForSession(ConfigParam::SEARCH_PATH, 'public, other');

$timeout = $cfg->get(ConfigParam::STATEMENT_TIMEOUT); // returns a Quantity object
echo $timeout->convert(Quantity::SECOND) . PHP_EOL; // prints "60 s"

{% endhighlight %}
* Distinction between session- and transaction-wide values. Support for custom, user-defined variables.
```php
<?php
$cfg = $conn->getTxConfig();
$tx = $conn->startTransaction();
$cfg->setForTransaction('ivory.foo', 'bar');
// The value is accessible by PostgreSQL:
var_dump($conn->querySingleValue("SELECT current_setting('ivory.foo')")); // prints "bar"
// ...and by the config object, too:
var_dump($cfg->get('ivory.foo')); // prints "bar"
// After rollback, the value gets dropped by PostgreSQL:
$tx->rollback();
var_dump($cfg->get('ivory.foo')); // prints ""
```

### User-Definable Exceptions on Certain Database Errors
* User-defined classes of exceptions may be defined to be thrown upon a database error of a given SQL state code or of
  the error message matching a given pattern. One may thus directly catch only the interesting exceptions using specific
  `catch` blocks.
{% highlight php %}
<?php
class NotNullViolationException extends \Ivory\Exception\StatementException
{
}

$exFactory = $conn->getStatementExceptionFactory();
$exFactory->registerBySqlStateCode(SqlState::NOT_NULL_VIOLATION, NotNullViolationException::class);

try {
    $conn->command('INSERT INTO album (name) VALUES (NULL)');
} catch (NotNullViolationException $e) {
    echo $e->getMessage(); // prints "null value in column "name" violates not-null constraint"
}
{% endhighlight %}

### Asynchronous Connection
* By default, Ivory connects to the database asynchronously. Only the first attempt to actually use the database blocks
  until the connection is established.
  * The optimal usage is to start connecting to the database as soon as the configuration is known, loading other
    libraries or parsing the input meanwhile, then actually performing the requested job once the connection is ready.

### Asynchronous Querying
* Making queries or commands in the background is as easy as calling `queryAsync()` instead of `query()` (or
  `commandAsync()` instead of `command()`, respectively) and using `getResult()` once the result is really needed. In
  the meantime, the PHP process may do more useful job than just waiting for the database result to be ready.

### Customizability
* No magic hardwired in Ivory to support the standard features. The standard configuration is assembled in a single
  place, inviting the user to override or change implementation of almost anything, including the behaviour of data type
  converters, SQL pattern macros or hooks being called upon changes of session settings or transaction status.

### Clean API
* Strict distinction between _queries_ (which produce relational data) and _commands_ (which just do the desired effect
  and report on its success).
* Wherever possible, method arguments and return values are typed.
  [`strict_types`](http://php.net/manual/en/functions.arguments.php#functions.arguments.type-declaration.strict) are
  used.
* Values represented by immutable objects.

### Caching
* Ivory may use any cache implementing the [PSR 6 Caching Interface](http://www.php-fig.org/psr/psr-6/).

### Relation Algebra Operators
* Standard relational algebra operations are defined on data sets (*relations*): projection, filtering, unique, rename
  or grouping are all available on the PHP side.<sup>[1](#footnote1)</sup>
* Relations themselves may be used just as any simple value for building further queries.

### Cursors
* Cursors may be declared, listed or disposed using an object API.
* Any cursor, either declared with Ivory or taken over from a
  [`refcursor` value](https://www.postgresql.org/docs/current/plpgsql-cursors.html#PLPGSQL-CURSOR-DECLARATIONS), is
  encapsulated in an `ICursor` object, through which it may easily be manipulated and iterated on.
  * The `IteratorAggregate` interface is implemented in an extensive way by `ICursor`: as an optional argument, buffer
    size may be given. The rows are then fetched in batches, each batch retrieved in the background (using asynchronous
    queries) while processing the previous batch.
  
### Inter-Process Control
* The inter-process communication primitives `LISTEN` and `NOTIFY` are encapsulated in an object API.



## Features Planned for Further Releases

### <!-- #7 --> List Serializers
* Implement serializers for lists of values, useful for IN clauses.<sup>[2](#footnote2)</sup>
* Especially, implement "li" (comma-separated list of "i") and "ls" (comma-separated list of strings).

### High-Level Transaction API
* <!-- #6 --> Emulate nested transactions.

### <!-- #13 --> BLOBS
* Implement support for large binary objects.

### <!-- #14 --> Prepared Statements
* Besides Ivory's SQL Patterns, also support the true prepared statements to allow for the performance gain.
* Ideally, make it automatic, for Ivory to decide which SQL Pattern parameter may be passed to PostgreSQL as a prepared
  statement argument, and which must be serialized to a literal.

### <!-- #9 --> Database Introspector
* Currently, the database introspector is used internally to recognize data types only. That might be extended to
  provide meta-info on database objects of all kinds, inviting the user to do meta programming.

### <!-- #8 --> Encapsulation of Database Function Calls
* Introduce methods to call database functions from PHP code directly, not by composing the function call by hand.
* Allow one to define classes representing database functions (regardless of whether permanent or temporary), and
  instantiating them to call them.
* Distinguish void, scalar, and set-returning functions. That would either lead to a relation definition or command,
  which would also be used in more complex queries. Scalar function results might be used as parameters in complex
  queries, or as just a value to fetch.

### <!-- #20 --> Copy Control
* Encapsulate the `COPY` command in an object API.

### <!-- #15 --> Catch All PostgreSQL Notices
* Catch all notices raised during an executed statement. Currently, only the last notice is caught due to the `pgsql`
  driver limitations.

### IDE Support for SQL Patterns
* As a side project, implement plugins to PHP IDEs to process SQL patterns correctly.

  
## Suggestions?

Any features missing? Don't hesitate to suggest some using the
[GitHub issue tracker](https://github.com/ondrej-bouda/ivory/issues)!


___

<small>
<a name="footnote1"><sup>1</sup></a>
While such operations should usually be performed at the PostgreSQL side, they might be useful at the PHP side, too,
especially on small relations or when the same query result is used for multiple purposes.
<br>
<a name="footnote2"><sup>2</sup></a>
Although arrays are more useful as they do not make a syntax error if empty.
<br>
</small>
