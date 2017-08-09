---
layout: default
---

# Documentation
{:.no_toc}

On this page, documentation on selected topics is provided. Together with the API documentation, this is the
most detailed documentation, suitable for users who have already [got started](getting-started.md) with Ivory and are
familiar with its [features](features.md). The topics are ordered such that forward references are minimized, so the
ideal way is to read it from the start to the end.
 
API documentation is not available online yet. Please, see the
[source code](https://github.com/ondrej-bouda/ivory/tree/master/src/Ivory).
Usually, the code is divided into interfaces and standard implementations which do not duplicate the API documentation.
Thus, consult the interfaces documentation where possible.

Table of contents:
* TOC
{:toc}



## Connection Management

Connection to a database is represented by an `IConnection` object. It is the single entry point to the most of Ivory
functionality. For organizational purposes, the big `IConnection` interface is split into several logical groups, which
we will refer to throughout the documentation:
* `IConnectionControl`, responsible for [establishing the connection](#connecting-to-a-database) to the database server;
* `ITypeControl`, managing [data types](#data-types);
* `ISessionControl`, controlling
  [runtime parameters of the established database session](#database-session-configuration-variables);
* `IStatementExecution`, encapsulating [execution of queries and commands](#statement-execution);
* `ITransactionControl`, encapsulating [transaction management](#transactions);
* `ICursorControl`, managing [cursors](#cursors);
* `IIPCControl`, providing an object API for [inter-process communication](#inter-process-communication-support)
   primitives offered by PostgreSQL;
* `ICacheControl`, fulfilling [caching](#caching) needs of other Ivory parts.
<!-- TODO #20
  `ICopyControl`, grouping together functionality around [copying data](#copying-data) from/to a database, using the
  `COPY` command;
-->

A connection object may be retrieved using the `Ivory::setupNewConnection()` method, which:
1. creates a new connection with the specified parameters,
2. registers the connection in the global connection storage.

Ad 1, the connection parameters may be specified by a `ConnectionParameters` object, or by anything accepted by the
`ConnectionParameters::create()` factory method. All common specification formats are supported:
* a <a href="https://www.postgresql.org/docs/11/libpq-connect.html#LIBPQ-CONNSTRING" target="_blank"> PostgreSQL
  connection string</a>, e.g., `"host=localhost port=5432 dbname=mydb connect_timeout=10"`;
* an <a href="https://www.postgresql.org/docs/11/libpq-connect.html#LIBPQ-CONNSTRING" target="_blank">RFC 3986 URI</a>,
  e.g., `"postgresql://usr@localhost:5433/db?connect_timeout=10"`;
* a map of
  <a href="https://www.postgresql.org/docs/11/libpq-connect.html#LIBPQ-PARAMKEYWORDS" target="_blank">connection
  parameter keywords</a> (the usual ones defined as `ConnectionParameters` constants) to values, e.g.,
  `[ConnectionParameters::HOST => '/tmp']`.

Ad 2, the purpose of the global register is to make any database connection available from anywhere, using the static
method `Ivory::getConnection()`. It need not be used -- if the application uses dependency injection or a similar
mechanism, it is sufficient to just create an `IConnection` object by hand, instead of using
`Ivory::setupNewConnection()`. The connection register is here just to simplify connection retrieval, and no other Ivory
part depends on it.

Multiple connections may be registered, distinguished by their name specified by an optional argument to
`Ivory::setupNewConnection()` (if no name is specified explicitly, a unique name is generated automatically). In most
applications, however, just a single connection is needed. Thus, the first connection registered with Ivory
automatically becomes the default one. The `Ivory::getConnection()` method, if not given a connection name, returns the
default connection.

```php
<?php
$firstConn = Ivory::setupNewConnection('host=localhost dbname=mydb connect_timeout=10');
$secondConn = Ivory::setupNewConnection('postgresql://usr@localhost:5433/otherdb', 'other');

// The following assertions hold:
assert($firstConn === Ivory::getConnection()); // the first is the default
assert($secondConn === Ivory::getConnection('other')); // identified by the given name
assert($firstConn === Ivory::getConnection('mydb')); // name generated from the database name
```

Any other connection may be marked as the default at any time using `Ivory::useConnectionAsDefault()`.

For certain applications, `Ivory::dropConnection()` method may be relevant. It drops the given connection so that it
does not occupy memory anymore.



## Connecting to a Database

Having defined one or more connections, we can instruct them to actually connect to the database. The control over
connection is grouped in methods implementing the `IConnectionControl` interface. Usually, just one of them will really
be used:
```php
<?php
$conn->connect();
```
The `IConnectionControl::connect()` method returns immediately while the connection is being established in the
background. In the meantime, the application may do jobs not requiring database, such as routing or input processing,
which may save time.

The very first method really requiring the connection, such as `IConnection::query()`, will wait if the connection has
not been established yet. Thus, the asynchronous connecting mode is totally transparent to the user. Still, if
synchronous connection is required for some reason, the `IConnectionControl::connectWait()` method may be used instead,
which will block the execution until the connection is established.

Several hooks, used internally by Ivory, but also available to the public, may be registered for certain events:
* `IConnectionControl::registerConnectStartHook()`: immediately after starting the asynchronous connecting;
* `IConnectionControl::registerPreDisconnectHook()`: right before the connection gets closed;
* `IConnectionControl::registerPostDisconnectHook()`: right after the connection gets closed.

To disconnect (which is typically unnecessary as PHP frees all the resources at the end of the script), the
`IConnectionControl::disconnect()` method is available.

Note that there is no option for using connections persistent on the PHP side (i.e., those done by `pg_pconnect()`) as
this feature is known to be neither 100% correct nor especially effective. Server-side connection pooling shall be
considered instead.



## SQL Patterns

In order to execute statements through a connection, one must construct them first. The trivial way is to compose an SQL
query string somehow and pass it to `IStatementExecution::rawQuery()` or `IStatementExecution::rawCommand()` (more on
that in the [Statement Execution](#statement-execution) section):
```php
<?php
$rel = $conn->rawQuery('SELECT * FROM t');
foreach ($rel as $row) {
    // ...
}
```
Constructing SQL strings by hand, however, is painful and error-prone.<sup>[1](#footnote1)</sup> Using _SQL patterns_,
Ivory may assist with the task.

SQL patterns is a special macro language over SQL, defined by Ivory. The idea is to let the user type exactly the SQL
she wants, and to assist with connecting the SQL and the PHP worlds, i.e., pass PHP values to the SQL string.
 
The language is very simple: all text is interpreted as is, except `%` (percent sign) characters, optionally followed by
several tokens, which denotes a _placeholder_ that will eventually be replaced with a PHP value serialized to SQL. It is
similar to the `sprintf()` function except for what may follow the `%` sign and what it means. For example, with:
```php
<?php
$rel = $conn->query('SELECT * FROM t WHERE name = %s', "O'Reilly");
```
the resulting query sent to the database is `SELECT * FROM t WHERE name = 'O''Reilly'`. A detailed specification
follows.


### Placeholder Specification

Placeholders specify the location in the SQL string for inserting the statement parameter values. There are two kinds of
parameters:
* _named parameters_ -- these are specified by an explicit name; and
* _positional parameters_ -- specified solely by their position relative to other positional parameters.

The placeholder syntax is as follows:
```
%[type][?][:name]
```
where:
* `type` is an explicit type specification, governing how the parameter value will be encoded to the SQL string (if not
  given, the type is inferred from the actual data type of the parameter value -- also referred to as _auto-typed_);
* the question mark specifies to omit explicit typing when serializing the value (typically, values will be serialized
  as mere string literals containing the representation of the serialized value, from which PostgreSQL will coerce to
  the right type; e.g., `%);
* `name` is the name of the parameter (if not specified, the parameter is treated as positional).

Examples:
* `%` -- auto-typed positional parameter
* `%t` -- positional parameter of type `t` (withing the current search path)
* `%:tbl` -- auto-typed parameter named `tbl`
* `%public.planet:p1` -- parameter named `p1` of type `public.planet`
* `%public.planet[]` -- positional parameter of type `public.planet[]`
* `%int_singleton` -- positional parameter of type `int_singleton`
* `%int[][]` -- positional parameter of type `int[][]`
* `%public."int":person_id` -- parameter named `person_id` of type `public.int`
* `%"my schema"."my type"` -- positional parameter of type `my type` within the `my schema` schema
* `%{double precision}` -- positional parameter of type `double precision`
* `%{double precision}?` -- positional parameter of type `double precision` left for PostgreSQL to coerce to the right
  type on its own either to `integer`, `bigint` or `numeric` (see
  [Numeric Constants](https://www.postgresql.org/docs/11/sql-syntax-lexical.html#SQL-SYNTAX-CONSTANTS-NUMERIC))

A more detailed specification follows:
1. We define a _token_ as a sequence of one or more letters, digits and underscore characters (`_`), not starting with a
   digit. The terms "letters" and "digits" correspond to the
   [PCRE character class](http://php.net/manual/en/regexp.reference.character-classes.php) _alnum_.
2. `name` may only be a single token. Especially, `name` may not be a number (i.e., referring to positional arguments is
   not supported).
3. `type` consists of either:
   - sequence of `typeschema.typename`, or
   - just `typename`, or
   - any string enclosed in a pair of curly braces (the `{` and `}`; note there is no way to write the closing brace
     literally using this variant).
4. Both `typeschema` and `typename` may either be:
   - a single token, or
   - any string enclosed in double quotes (the `"` character; to write a double quote character literally inside the
     quoted string, use two of them).
5. `type` may optionally be appended with one or more empty pairs of square brackets, indicating an array type. Multiple
   bracket pairs are accepted although they are semantically equivalent to just a single pair, consistently with
   PostgreSQL. Note that only _empty_ pairs of brackets are recognized as part of the type specification; an array
   placeholder immediately followed by an array subscript works as expected: in `SELECT %bigint[][2]`, just the
   `%bigint[]` marks the placeholder, and thus the statement selects the item at index 2 from the provided array
   parameter.
6. To write `%` literally within the SQL string, type `%%`.
7. The percent signs, denoting the placeholders, are searched in the whole string, regardless of the surrounding
   content (e.g., `SELECT '%s'` will usually be wrong).

The semantics for the placeholder name is trivial:
* if `name` is given, the value of the statement parameter named `name` is used;
* otherwise, the value of the corresponding positional parameter is considered.

Semantics for the placeholder type is more complicated.
* If `typeschema.typename` is used, the type `typename` from schema `typeschema` is used.
* If just `typename` is used, the following cases are tried consecutively:
  1. a special value serializer `typename` (e.g., `sql` is a special serializer, passing the parameter value as is),
  2. a type alias `typename` (e.g., `int` could be an alias of `pg_catalog.int8`),
  3. a type `typename` found using the PostgreSQL `search_path` facility (the `search_path` currently valid within the
     connection is used).
* If the `typeschema` or `typename` is not double-quoted, it is searched in case-insensitive fashion.
* If enclosed in double quotes, the schema/type name comparison is case sensitive and, if just `typename` is used,
  neither special value serializers nor type aliases are considered<sup>[2](#footnote2)</sup>.
* The curly braces syntax is equivalent to specifying just an unquoted `typename` -- it merely allows one to specify
  more than just a single token, e.g., `{double precision}`.
* If `type` is omitted completely, the type is inferred automatically from the parameter value. See section
  [Type Inference Rules](#type-inference-rules) for more on that.

Note that the SQL pattern language itself does not define any actual data types or type inference rules. These are
defined by the `Ivory\Type\TypeDictionary` used by the connection for which the SQL pattern is being serialized to an
SQL string. In other words, it is up to the connection whether a type is available -- see
[Data Type Dictionary](#data-type-dictionary). The default Ivory configuration should be sufficient in most situations
however:
* the database is searched automatically for any defined data types;
* the standard PostgreSQL type aliases (such as `INT`) are registered;
* type inference rules are configured to handle the base PHP types `int`, `string`, `bool`, `float` and `array`, and to
  also cover most of the `Ivory\Value` objects;
* several type abbreviations are defined:
  * `s` for `pg_catalog.text`,
  * `i` for `pg_catalog.int8` (i.e., `BIGINT`),
  * `num` for `pg_catalog.numeric`,
  * `f` for `pg_catalog.float8` (i.e., `DOUBLE PRECISION`),
  * `ts` for `pg_catalog.timestamp`,
  * `tstz` for `pg_catalog.timestamptz`;
* several special serializers are defined:
  * `sql` -- pass along the given value as is,
  * `ident` -- treat the value as an identifier (table name, column name, etc.),
  * `rel` -- construct a table expression of rows from the `IRelation` or `IRelationDefinition` value,
  * `cmd` -- serialize the `ICommand` value to the SQL string,
  * `like` -- treat the value as a `LIKE` argument with no wildcards,
  * `like_`, `_like`, `_like_` -- `LIKE` argument with `%` wildcard on the right, on the left, or on both sides,
    respectively.

Beware of stating non-schema-qualified types followed by a dot and a name. This is typical when qualifying column names
with the table name, e.g., `person.name`. To parametrize the table name, use `%{ident}.name` to prevent Ivory to search
for type `name` in schema `ident`.


### Comparison with Prepared Statements

Note that, although similar, SQL patterns have nothing to do with prepared statements. The whole SQL string is composed
by Ivory and sent to the database server as is. This has the advantage of parametrizing the statement in any part (e.g.,
for table name), as opposed to prepared statements which are rather limited.

To reduce the overhead, Ivory caches the SQL patterns parsed from the given strings. For that to work, a cache
implementation must be provided, as suggested in the [Caching](#caching) section. Still, the database server must parse
the received query string and must compute the query plan over and over again. To remedy that, Ivory is planned to
support prepared statements in a future version (see [#14]). The different syntax should allow one to even combine
prepared statements with SQL patterns.



## Statement Execution

For type safety and transparency reasons, Ivory strictly distinguishes the executed statements to:
* _queries_, which result in a relation, and
* _commands_, the result of which is just a summary information, such as the number of affected rows in a table.

Using the `IStatementExecution::query()` method with an SQL statement not returning data set (such as a plain
`INSERT` without the `RETURNING` clause) is an error. Similarly, calling `IStatementExecution::command()` with an SQL
statement returning data (such as `SELECT`) is an error, too. Both will result in a `UsageException`.


### Query()

As arguments, several different kinds of things may be given to `query()`:
1. Plain string, which is interpreted as an [SQL pattern](#sql-patterns), followed by values for its positional
   parameters, and a map of values for its named parameters (if any):
```php
<?php
$conn->query('SELECT 42');
$conn->query('SELECT %int', 42);
$conn->query('SELECT %:pi * %:pi / 6', ['pi' => 3.14]);
$conn->query(
    'SELECT * FROM %ident WHERE a = %int:a AND b = %s:b',
    't',
    ['a' => 42, 'b' => 'wheee']
);
```
   * Exact number of values must be provided, matching the number of positional parameters in the SQL pattern.
   * Likewise, the map of named parameter values must be provided (as the last argument) if the SQL pattern uses some
     named parameters. Ivory is strict here: even values of parameters which are unused in the SQL pattern lead to an
     `\InvalidArgumentException`.
2. Several SQL pattern string fragments, which get glued together (using a single space), each followed by its
   positional parameters, and the named parameters at the very end:
```php
<?php
$conn->query(
    'SELECT 2 * %:pi * radius FROM %ident', 'tbl',
    'WHERE a = %int AND b = %s', 42, 'wheee',
    ['pi' => 3.14]
);
```
3. Already parsed `SqlPattern` objects in place of string SQL patterns, each followed by values of its positional
   parameters, ended with values for named parameters, as described above.
4. An `SqlRelationDefinition` object, with a map of values for named parameters which have not been set up yet:
```php
<?php
$relDef = \Ivory\Query\SqlRelationDefinition::fromPattern('SELECT %:a + %:b');
$relDef->setParam('a', 3);
$conn->query($relDef, ['b' => 2]);
```
   * Values of parameters passed to `query()` (or `command()`) get precedence over those already set up on the
     `SqlRelationDefinition` object. The object is unmodified, though -- it still holds its own parameter values:
```php
<?php
$relDef = \Ivory\Query\SqlRelationDefinition::fromPattern('SELECT %:a + %:b');
$relDef->setParams(['a' => 1, 'b' => 2]);
$conn->query($relDef, ['b' => 3]); // SELECT 1 + 3
$conn->query($relDef); // SELECT 1 + 2
```
   * Note to use `SqlRelationDefinition::fromFragments()` to build the definition from multiple fragments as in 2:
```php
<?php
$relDef = \Ivory\Query\SqlRelationDefinition::fromFragments('SELECT %ident', 'col', 'FROM %ident', 'tbl');
$conn->query($relDef);
```
5. Some other `IRelationDefinition` object. No additional arguments are expected in this case.

As a result, `query()` returns an `IQueryResult`, which is both an `IRelation` (more on that in the section
[Relations](#relations)) and an `IResult`. If the query produced a notice, `IResult::getLastNotice()` may be used to get
it.


### Query() Specializations

In case only a single row (or tuple, in terms used by Ivory), a single column, or just a single value is expected, one
may use handy specializations. They are used similarly to the `query()` method, but impose a condition on the result:
* `querySingleTuple()`: the query must result in just a single tuple, which gets returned as an `ITuple` object:
```php
<?php
$t = $conn->querySingleTuple('SELECT a, b, c FROM t LIMIT 1');
echo $t->a; // prints the t.a value
```
* `querySingleColumn()`: the query result must only contain a single column, which gets returned as an `IColumn` object:
```php
<?php
$col = $conn->querySingleColumn('SELECT a FROM t');
echo $col[0]; // prints the first value of t.a
```
* `querySingleValue()`: the query result must only contain a single row with a single column, the value of which is
  returned:
```php
<?php
$val = $conn->querySingleValue('SELECT 3.14');
echo $val; // prints 3.14
```

All the methods throw a `ResultDimensionException` when the condition is not met. That way, the caller may be sure the
query is specified as really intended. If this is too strict, one may use just the `query()` method and call the
`IRelation::tuple()`, `IRelation::col()` or `IRelation::value()` method on the result.


### QueryAsync()

To run a query asynchronously, call `queryAsync()` instead of `query()`. It will send the query to the database and
return immediately, without waiting, an `IAsyncQueryResult` object. Once the result is really needed,
`IAsyncQueryResult::getResult()` may be called, which will wait for the result if not yet ready, and process it to an
`IQueryResult`, just as synchronous `query()` would do.
```php
<?php
$asyncRes = $conn->queryAsync('SELECT generate_series(1, 100000)');
// do other work not involving the same connection
$res = $asyncRes->getResult();
// process $res as usual:
foreach ($res as $tuple) {
    echo $tuple[0] . ' ';
}
```
Note a caveat: while waiting for the result, the database must not be worked with using the same connection. Otherwise,
the query results might get mixed.


### Command()

Using `IStatementExecution::command()` is similar to `query()`:
1. Same as for `query()`, e.g.:
```php
<?php
$conn->command('INSERT INTO %ident (%ident) VALUES (%)', 't', 'name', 'Agung');
```
2. Same as for `query()`, e.g.:
```php
<?php
$conn->command('UPDATE %ident', 't', 'SET a = %', 42);
```
3. Same as for `query()`.
4. Similar to `query()`, just `SqlCommand` is used instead of `SqlRelationDefinition`:
```php
<?php
$cmd = \Ivory\Query\SqlCommand::fromPattern('INSERT INTO t VALUES (%)', 3);
$conn->command($cmd);
```
5. Similary to `query()`, just `ICommand` is used instead of `IRelationDefinition`.

Result of a successful `command()` call is an instance of `ICommandResult`. It extends the common `IResult`
functionality with command-specific features, such as `ICommandResult::getAffectedRows()`.


### CommandAsync()

The `commandAsync()` method executes a command in the background, without waiting for the result. Returned is an
`IAsyncCommandResult`, the `getResult()` of which will wait for finalizing the command and returning the result as an
`ICommandResult` object. Otherwise, the usage is the same as of `command()`.

The same caveat is here as for `queryAsync()`: do not further query the database with the same connection until you call
`getResult()` on the asynchronous result object, or the results might get mixed.


### Other Execution Methods

In case you already have an SQL statement ready in the string form, just pass it to
`IStatementExecution::rawQuery()`, or `IStatementExecution::rawCommand()`, respectively. The result, including possible
exceptions, is the same as in case of `query()` and `command()`.
```php
<?php
$conn->rawQuery("SELECT * FROM person WHERE name ILIKE '%doe%'");
```

If the SQL statement comes from input, and thus the program does not know whether it is actually a query or command,
the general method `IStatementExecution::executeStatement()` will come in handy:
```php
<?php
$conn->executeStatement('CREATE TABLE t (a INT)'); // IResult; in this case, it is an ICommandResult
```

Just as for `query()` and `command()`, there is also an asynchronous version for the generic `executeStatement()`
method, returning an `IAsyncResult`, the `getResult()` method of which will, in turn, wait and return an `IResult`
object after the result is ready:
```php
<?php
$asyncRes = $conn->executeStatementAsync("INSERT INTO t (a) VALUES ('foo')");
// instead of waiting, use the time on more reasonable things
$asyncRes->getResult();
// now we can continue querying the database
```

All the execution methods discussed so far allow to execute just one statement at a time. To execute an SQL script,
which contains multiple statements separated with semicolons, use `IStatementExecution::runScript()`. A list of results,
one for each executed statement, is returned:
```php
<?php
$results = $conn->runScript(
    'CREATE TABLE t (a INT);
     INSERT INTO t (a) VALUES (1), (2);
     SELECT * FROM t'
);
assert($results[2] instanceof \Ivory\Result\IQueryResult);
$vals = $results[2]->col('a')->toArray(); // $vals is an array [1, 2]
```


### Error Handling

If the executed query is wrong, or if a constraint is violated, or in hundreds of other cases, the database server
raises an exception. On the PHP level, Ivory throws a `StatementException`, representing the database error. The
exception holds several useful attributes:
* The query which caused the error, retrieved by `StatementException::getQuery()`.
* The error message, returned by `StatementException::getMessage()`.
* SQL state, represented by an `SqlState` object returned by `StatementException::getSqlState()`, or just the SQL state
  code returned by `StatementException::getSqlStateCode()`. That may be compared with one of `SqlState` constants to
  recognize the error type (see also [Custom Exceptions](#custom-exceptions) below).
* All other details provided by PostgreSQL: severity, message detail, hint, statement position, internal query and
  position (e.g., an erroneous statement issued from within a PL/pgSQL function), and error context (call stack) -- see
  the `StatementException` API.

Rarely:
* a `ConnectionException` will be thrown if the query could not be sent or the result could not be received;
* an `InvalidResultException` will be thrown in case the query was executed but the result is broken.

As described above, logic exceptions of class `UsageException` or `ResultDimensionException` may also be thrown (the
latter just from queries, not commands). 


#### Custom Exceptions

Often, different types of database errors are handled differently. For example, to insert data to a unique-constrained
table, one can just try to `INSERT` the data and handle a unique violation error gracefully (before PostgreSQL 9.5
introduced the `INSERT ... ON CONFLICT` variant, this was the only way to do that in a race-free manner). Or in case the
query has been cancelled (e.g., due to statement timeout), the program might retry the query. The application might even
define its own error codes, which could be handled differently from the standard ones.

All these situations would call for catching the `StatementException`, test the SQL state, and either process the case,
or rethrow it up. Such approach leads to a plenty of boilerplate, procedural-style code, however.

Ivory does not try to define special subclasses of exceptions to represent various types of errors. Instead, the
application may define its own subclasses of `StatementException` and instruct Ivory to throw them in specific cases.

A specific kind of statement error may be recognized by its SQL state, by the error message, or by a combination of
both. The `SqlState` class defines constants for SQL state codes
[implemented by PostgreSQL](https://www.postgresql.org/docs/11/errcodes-appendix.html) -- these may be used for
registering a custom exception class with either the local (connection-wide) or global `StatementExceptionFactory`:
```php
<?php
$exFactory = $conn->getStatementExceptionFactory();
// or use Ivory::getStatementExceptionFactory() for the global exception factory

$exFactory->registerBySqlStateCode(
    \Ivory\Lang\Sql\SqlState::FOREIGN_KEY_VIOLATION,
    ForeignKeyViolationException::class
);
// from now on, foreign key violations will be represented by ForeignKeyViolationException

$exFactory->registerBySqlStateClass(
    \Ivory\Lang\Sql\SqlStateClass::INTEGRITY_CONSTRAINT_VIOLATION,
    ConstraintViolationException::class
);
// from now on, any constraint violation error will be represented by ConstraintViolationException
// ...except foreign key violations, which are preferred to be handled by ForeignKeyViolationException
```

Recognizing the error by the primary error message is considered as a bad practice<sup>[3](#footnote3)</sup>.
The support in Ivory is meant as just a supplement to the SQL state-based recognition. Multiple error types of the same
SQL state code may be differentiated by the primary message using
`StatementExceptionFactory::registerBySqlStateCodeAndMessage()`. Or, if the database error does not get matched by any
SQL state code or class, the last try is made using solely the primary message rules registered by
`StatementExceptionFactory::registerByMessage()`. For both the methods,
[Perl-compatible regular expressions](http://php.net/manual/en/book.pcre.php) are considered.

As for many other Ivory parts, there are two levels where the exception factory may be configured. Primarily, the
database error is handled by the connection-local factory, as illustrated by the examples above. If no rules match the
error, the global factory is used as a fallback. That is retrieved by calling `Ivory::getStatementExceptionFactory()`
and is useful for defining custom exception rules common for multiple connections. If no matching rules are defined even
at the global level, a `StatementException` is thrown.

Note it is necessary for the custom exception classes to subclass from `StatementException`. Please, follow its
[class documentation](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Exception/StatementException.php).



## Relations

Ivory uses the term _relations_ instead of _data sets_ not to look more scientific, but to refer to relational algebra.
The idea is that a relation might not only come from the result of a query, but may also arise otherwise. E.g., one may
construct an ad hoc relation from a bunch of values, or it may be retrieved from cache or received from input. As such,
relations are treated as first-class citizens in Ivory, and have their own set of operations, similar to those in
PostgreSQL, such as projection, rename, filter, etc. They can even be passed to SQL patterns using the `%rel`
serializer.


### Constructing Relations

The majority of relations will come from the database queries, returned by the `IConnection::query()` method. Besides, 
one can construct an ad hoc relation directly in PHP, using `ArrayRelation`:
```php
<?php
$relation = ArrayRelation::fromRows(
    [
        [1, 'a', 3.14, false, null],
        [5, 'g', 2.81, true, 'text'],
    ]
);
```
Above, a relation consisting of 5 columns and 2 rows gets constructed. Column types are inferred automatically from
values, just as in case of [auto-typed SQL pattern parameters](#placeholder-specification).

The column types may be specified explicitly, using the optional `$typeMap` argument. Also, the columns may get custom
names, and not just ordinal numbers:
```php
<?php
$relation = ArrayRelation::fromRows(
    [
        ['a' => ['x' => 5, 'y' => 7], 'b' => null, 'c' => 111, 'd' => 'NULL'],
        ['a' => null, 'b' => 'foo', 'c' => 114, 'd' => 'EXISTS(SELECT FROM foo)'],
    ],
    ['a' => 'public.hstore', 'b' => null, 'c' => 'i', 'd' => 'sql']
    // type of "b" gets inferred from values
    // values from "d" are considered to be SQL expressions which get serialized as is
);
```

[PHPDoc for `ArrayRelation::fromRows()`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Relation/ArrayRelation.php)
describes everything in more detail.


### Relational Operations

Usually, relations are manipulated at the database side using the means of SQL. In certain cases, however, further
manipulation at the PHP side might be handy. A typical case is extension of a relation received from the database with
a new column computed by PHP. This may be done fairly easily:
```php
<?php
$relation = $conn->query('SELECT * FROM person');
$ext = $relation->extend([
    'age' => function (ITuple $tuple) {
        return (new \DateTime())->diff($tuple['date_of_birth']->toDateTimeImmutable());
    },
]);
// now, $ext contains all data of $relation plus the "age" column with DateInterval objects
```
The code above uses the fact that the `IQueryResult` object implements the `IRelation` interface, and thus may be
treated as a relation right away.

Further `IRelation` operations include:
* `filter()` -- PHP side filtering of rows (helpful when using a single query result for multiple times);
* `project()` -- defines new columns based on the original ones;
* `rename()` -- renames columns;
* `uniq()` -- removes duplicate rows.

Note that neither operation modifies the relation. Instead, a new relation is created, which allows reuse of the
original relation for different purposes.

Besides relational operations, some more methods are defined on relations, making it easier to process them:
* `tuple()` takes one [tuple](#tuples) from the relation;
* `col()` extracts a single, iterable column of the relation or defined ad hoc, using a `ITupleEvaluator` or `Closure`,
  similarly to the `extend()` usage example above:
```php
<?php
$rel = $conn->query('SELECT * FROM person');
$col = $rel->col('name');
foreach ($col as $name) {
    echo $name . PHP_EOL; // prints names of all people
}
```
* `value()` returns a single value from a given column and tuple;
* `assoc()` makes a map, using one or more columns as consecutive keys and another column as values:
```php
<?php
$rel = $conn->query('SELECT id, group_id, position, name FROM person');
$namesById = $rel->assoc('id', 'name');
echo $namesById[5]; // prints name of person of ID = 5
$namesByGroupAndPosition = $rel->assoc('group_id', 'position', 'name');
echo $namesByGroupAndPosition[2]['leader']; // prints the group 2 leader's name
```
* `map()` makes a map of whole tuples, using one or more columns as consecutive keys:
```php
<?php
$rel = $conn->query('SELECT id, group_id, name FROM person');
$map = $rel->map('id');
echo $map[5]->name; // prints name of person of ID = 5
```
* `multimap()` makes a multimap, i.e., a map which does not eliminate duplicates but holds a list of tuples in the last
  dimension (essentially, it splits the relation into multiple small relations of items of the same key):
```php
<?php
$rel = $conn->query('SELECT id, group_id, position, name FROM person');
$multimap = $rel->multimap('group_id', 'position');
foreach ($multimap as $groupId => $group) { // prints members by groups
    echo "Group $groupId:" . PHP_EOL;
    foreach ($group as $position => $persons) {
        echo "  $position:" . PHP_EOL;
        foreach ($persons as $person) {
            echo '  - ' . $person->name . PHP_EOL;
        }
    }
}
```
* `toSet()` makes a set of values from the relation, which is specialized to tell whether there was a given value within
  the relation:
```php
<?php
$rel = $conn->query('SELECT id, group_id, name FROM person');
$groupSet = $rel->toSet('group_id');
print_r($groupSet->contains(5)); // tells whether there is someone from group 5
```
* `toArray()` converts the relation to a list of associative arrays, each a map of column names to values.


### Tuples

Data rows, or _tuples_, are represented by instances of `ITuple`. The individual fields (values of the columns) are
accessed using the object attribute syntax:
```php
<?php
$tuple = $conn->querySingleTuple('SELECT * FROM person WHERE id = %i', 123);
echo $tuple->name; // prints the person's name
```
Note that, in accordance with PostgreSQL, there may be multiple columns of the same name. When accessing the value by
column name, value of the first such named columns is returned.

Alternatively, a field value may be retrieved by the column offset using the array access syntax:
```php
<?php
echo $tuple[0]; // prints value of the first column
```

Method `ITuple::value()` accepts both the column name or offset, or any custom `ITupleEvaluator`.

All the tuple values may be exported to a plain list (using `toList()`) or to an associative array (using `toMap()`).


### Serializing Relations to SQL

As mentioned in the introduction to this section, there is a special `%rel` serializer shipped with Ivory. It serializes
a given relation to an SQL table expression. That allows one to, e.g., construct a
[common table expression](https://www.postgresql.org/docs/current/queries-with.html) using a relation:
```php
<?php
$arrRel = ArrayRelation::createAutodetect( // automatically detects types of columns
    [
        [1, 'a', 3.14, false],
        [5, 'g', 2.81, true],
    ],
    $conn->getTypeDictionary()
);
$conn->command(
    'WITH insert_data (id, name, value, is_active) AS (
         %rel
     )
     INSERT INTO t (id, name, value)
         SELECT id, name, value
         FROM insert_data
         WHERE is_active'
);
```


### Relation Definitions

While _relation_ is a set of the actual data, Ivory also recognizes _relation definitions_. A relation definition does
not hold the data, but serves as a prescription for what data to get.

An example is an `SqlRelationDefinition`, which essentially holds an SQL query string:
```php
<?php
$relDef = SqlRelationDefinition::fromPattern('SELECT %bool, %, %num', true, 'str', 3.14);
```

Relation definitions may be used for gradual specification of conditions, limitation/offset, and row order, as defined
by the `IRelationDefinition` methods:
* `where()` adds a condition which each tuple must satisfy;
* `sort()` specifies the sort criteria;
* `limit()` says the definition to only contain at most a given number of tuples, starting from an offset.

On top of these general operations, `SqlRelationDefinition` also implements `ISqlPatternStatement`, which allows the
definition to have parameters, the values of which may be given separately. This makes the `SqlRelationDefinition` a
suitable class for, e.g., specifying data sources of visual components.
```php
<?php
$personDef = SqlRelationDefinition::fromPattern('SELECT * FROM person WHERE group_id = %i:groupId');
$personDef->setParam('groupId', 3);
$rel = $conn->query($personDef);
foreach ($rel as $tuple) {
    echo $tuple->name . PHP_EOL; // prints names of people from the given group
}
```



## Data Types

The `%` placeholders in [SQL patterns](#sql-patterns) may refer to any data type defined in the database. E.g., the
`%date` placeholder refers to the `DATE` PostgreSQL type. The types are recognized automatically by the database
introspector, so even user-defined types may be used right away with no extra effort -- just define a composite,
enumeration, range or domain in the database and use them in SQL patterns, too.
```php
<?php
$conn->command("CREATE TYPE color AS ENUM ('red', 'green', 'blue')");
$c = $conn->querySingleValue('SELECT %color', 'red');
```

Besides refering to types by their names, several abbreviations are defined for frequently used ones:
* `s` ~ `pg_catalog.text`
* `i` ~ `pg_catalog.int8`, a.k.a. `BIGINT` 
* `num` ~ `pg_catalog.numeric`, a.k.a. `DECIMAL`
* `f` ~ `pg_catalog.float8`, a.k.a. `DOUBLE PRECISION`
* `ts` ~ `pg_catalog.timestamp`
* `tstz` ~ `pg_catalog.timestamptz`, a.k.a. `TIMESTAMP WITH TIMEZONE`

These are not hardwired, however. All built-in specifics are concentrated in the
[`StdCoreFactory`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/StdCoreFactory.php) class, and may either
be tweaked or completely overridden with another implementation of `ICoreFactory`.
```php
<?php
Ivory::getTypeRegister()->registerTypeAbbreviation('b', 'pg_catalog', 'bool');
```

For most non-trivial data types, rather than using standard classes (such as `\DateTime`), Ivory supplies special
classes of values. They are all declared within the
[`Ivory\Value`](https://github.com/ondrej-bouda/ivory/tree/master/src/Ivory/Value) namespace, and usually have adapter
methods to be converted from/to the standard ones (at the cost of losing precision). If rather the standard `\DateTime`
is required to be returned right away, it is not that difficult to redefine the corresponding type converter:
```php
<?php
$myDateType = new class('pg_catalog', 'date') extends \Ivory\Type\BaseType
{
    public function parseValue(string $extRepr)
    {
        return \DateTime::createFromFormat('!Y-m-d', $extRepr);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        if (!$val instanceof \DateTime) {
            throw new \InvalidArgumentException();
        }

        return $val->format("'Y-m-d'");
    }
};
Ivory::getTypeRegister()->registerType($myDateType);
```
> Note, however, that the `\DateTime` type does not support `-infinity` and `infinity` values, years exceeding four
> digits, stores the time part which is misleading when just the date is relevant... Which is why Ivory defines its own
> classes.

> Besides, `\DateTime` objects are not comparable by Ivory out of the box. Because of that, date ranges suddenly stopped
> being supported after registering the custom type above. See [Extending the Default Value Comparator](#extending-the-default-value-comparator) on how to
> resolve this problem.


### Type Registers

A `TypeRegister` is a collection of data type converters and their supplements for recognized PostgreSQL types. There
are two levels:
* a global type register, returned by `Ivory::getTypeRegister()`, and
* a connection-local type register, returned by `IConnection::getTypeRegister()`, having priority over the global
  register.

Type registers at both levels serve as basis for creating a [data type dictionary](#data-type-dictionary), which is
ultimately used for parsing and serializing values between PostgreSQL and PHP. More precisely, type registers keep all
definitions which are available to Ivory; the type dictionary is then created for the actually relevant types, loading
the type converters, abbreviations and other supplements from the connection-local and the global type register (in this
order).

The type dictionary is compiled by Ivory automatically when first needed, and is usually cached for the whole script
lifetime. Thus, it is necessary to register all necessary objects with type registers before any querying or commanding
the database.

The following subsections describe all kinds of objects kept by type registers.

#### Types and Type Loaders

The most important objects kept in type registers are `IType` and `ITypeLoader` objects. They may be registered using
the `TypeRegister::registerType()` and `TypeRegister::registerTypeLoader()` methods, respectively. A type or type loader
may also be unregistered using `TypeRegister::unregisterType()` and `TypeRegister::unregisterTypeLoader()`, although
that is usually unnecessary.

An `IType` object is registered by its name. If requested for a type dictionary, the `IType` object is returned as is.
Each type object provides two methods: `IType::parseValue()` and `IType::serializeValue()`, which get used for parsing a
value from and serializing it to the PostgreSQL _external_ representation.

An `ITypeLoader` is a lazy `IType` factory, only instantiating `IType` objects when asked. It implements just a single
method, `ITypeLoader::loadType()`, the purpose of which is to load a type converter of the requested name.

When a data type converter is requested, first, the connection-local type register is asked for its `IType` objects. If
no object of the requested name has been registered, the `ITypeLoader`s are asked in the order of registration. If no
matching type converter is still found, the analogical procedure is done with the global type register.

By default, Ivory registers `StdTypeLoader` as the only type loader at the global level. That type loader knows type
converters for all data types which are shipped with PostgreSQL.


#### Value Serializers

Besides `IType` objects, which are bi-directional converters between PHP and PostgreSQL values, type registers also
recognize value serializers, which merely help serialize PHP values to SQL strings. The
`TypeRegister::registerValueSerializer()` and `TypeRegister::unregisterValueSerializer()` method registers/unregisters a
value serializer.

When serializing a PHP value, the value serializer matching the requested name is tried first. Only if there is no
corresponding serializer, the dictionary is asked to provide an `IType`.

By default, Ivory registers several useful serializers. See [Placeholder Specification](#placeholder-specification).


#### Type Name Abbreviations

Data types might not only be referenced by their full name, but also aliased. Using
`TypeRegister::registerTypeAbbreviation()`, an abbreviation may be registered for a data type. The abbreviations are
then used in the type dictionary for type aliases, translating an alias to the original name. Due to the mechanism, it
is sufficient to use, e.g., `'%s'` in SQL patters instead of the full `%pg_catalog.text` type specification.

Note the type aliases are preferred over type converters, i.e., they are tried first for a match, only then real type
names are matched.

By default, Ivory registers several useful abbreviations globally, as listed [above](#data-types).


#### Type Inference Rules

The rules for deciding how an argument should be encoded for an auto-typed parameter (like in
`$conn->query('SELECT %', $arg)`) are also kept in type registers. A rule is simple: for a PHP type, it specifies the
qualified name of the corresponding PostgreSQL type. E.g., a rule for inferring integers is registered by:
```php
<?php
$typeRegister->registerTypeInferenceRule('int', 'pg_catalog', 'int8');
```

For object types, the PHP class name is expected:
```php
<?php
$typeRegister->registerTypeInferenceRule(\Ivory\Value\Date::class, 'pg_catalog', 'date');
```

A rule specifies, for a value of a given PHP type, to use the type converter of the given name. If exact match is not
found, rules for parent classes of the given object are tried, from the most specific class to the uppermost superclass.
As the last resort, rules for all interfaces implemented by the given object are attempted. E.g., by specifying
```php
<?php
$typeRegister->registerTypeInferenceRule(DateTimeInterface::class, 'pg_catalog', 'timestamp');
```
both PHP `\DateTime` and `\DateTimeImmutable` will be processed by the serializer for the `pg_catalog.timestamp` type.

Arrays are handled implicitly, the type is inferred by their first non-`null` element. See
[`TypeRegister::registerTypeInferenceRule()`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Type/TypeRegister.php)
for details.


### Data Type Dictionary

As already mentioned in previous sections, a type dictionary is compiled based on the global and connection-local
`TypeRegister` to contain type converters and supplements for data types used by a database.

One of the main reasons for separating type registers and type dictionary lies in a limitation of the `pgsql` PHP
extension -- for columns of a result set, it is able to provide either unqualified type names or type OIDs. The only way
to get to the fully qualified type names (and thus distinguish same-named types from different schemas) is to load the
mapping of OIDs to fully qualified names.<sup>[4](#footnote4)</sup> 

Also, subtypes of range and composite types are recognized by the introspection process, the result of which is an
`ITypeDictionary`.


### Value Classes

Most of the PostgreSQL data types do not have their equivalent in native PHP types, and even if there are some suitable
classes defined in the standard PHP library, their usage is rather limited. To support the most of the PostgreSQL type
system, Ivory comes up with its own classes for representing values of all standard PostgreSQL types.

All Ivory value classes are declared in the `Ivory\Value` namespace. Their common property is that they are immutable --
once constructed, the objects cannot change any of their attributes. Also, standard operations are defined on the value
classes to provide reasonable functionality, as well as adapter methods for converting Ivory values to other, more
frequently used values. In case some generally useful functionality is missing, contributions are welcome!


#### Date/Time Values

Of a special interest might be the date/time value classes. Although PHP has its `\DateTime` class, Ivory employs its
own set of classes, which offer all the features of the corresponding PostgreSQL date/time types:
* `-infinity` and `infinity` values,
* years beyond 9999,
* microsecond precision,
* negative and mixed intervals (e.g., `'-1 year -2 mons +3 days'`).

The Ivory classes are the following, representing exactly the PostgreSQL type values:
* `Ivory\Value\Date`: date without time;
* `Ivory\Value\Timestamp`: timezone-agnostic date/time;
* `Ivory\Value\TimestampTz`: timezone-aware date/time;
* `Ivory\Value\Time`: time without date;
* `Ivory\Value\TimeTz`: timezone-aware time without date;
* `Ivory\Value\TimeInterval`: interval of time.

All the classes are implemented such that the objects are comparable using standard PHP `<`, `==` and `>` operators,
with the expected results. Also, adapter methods are available to convert Ivory classes to `\DateTime`,
`\DateTimeImmutable` and UNIX timestamps. Still, if rather standard `\DateTime` or `DateTimeImmutable` are required,
regardless of their limitations, it is always possible (and, actually, very easy, as demonstrated in the
[Data Types introduction](#data-types)) to supply custom type converters for date/time types.


### Ranges

PostgreSQL comes with several range types and allows the user to create any custom ranges types (provided the subtype is
totally ordered). Ivory supports all of that, as demonstrated by the following code, resembling the
[PostgreSQL range examples](https://www.postgresql.org/docs/11/rangetypes.html#RANGETYPES-EXAMPLES):
```php
<?php
$roomReservationRange = Range::fromBounds(
    Timestamp::fromParts(2010, 1, 1, 14, 30, 0),
    Timestamp::fromParts(2010, 1, 1, 15, 30, 0)
);

// Containment
assert(Range::fromBounds(10, 20)->containsElement(3) === false);

// Overlaps
assert(Range::fromBounds(11.1, 22.2)->overlaps(Range::fromBounds(20.0, 30.0)) === true);

// Extract the upper bound
assert(Range::fromBounds(15, 25)->getUpper() === 25);

// Compute the intersection
assert(
    Range::fromBounds(10, 20)->intersect(Range::fromBounds(15, 25))
    ==
    Range::fromBounds(15, 20)
);

// Is the range empty?
assert(Range::fromBounds(1, 5)->isEmpty() === false);
```

Ivory automatically recognizes ranges of any subtype, provided it knows the subtype and it is totally ordered. It can
both parse and serialize the ranges from/to PostgreSQL:
```php
<?php
$conn->command('CREATE TABLE reservation (room int, during tsrange)');
$conn->command(
    'INSERT INTO reservation (room, during) VALUES (%i, %tsrange)',
    1108,
    Range::fromBounds(
        Timestamp::fromParts(2010, 1, 1, 14, 30, 0),
        Timestamp::fromParts(2010, 1, 1, 15, 30, 0)
    )
);
$resRange = $conn->querySingleValue('SELECT during FROM reservation WHERE room = %i', 1108);
$dayRange = Range::fromBounds(
    Timestamp::fromParts(2010, 1, 1, 0, 0, 0),
    Timestamp::fromParts(2010, 1, 2, 0, 0, 0)
);
assert($dayRange->containsRange($resRange) === true);
```

For creating ranges in PHP, the `Range::fromBounds()` method is available. It accepts the lower and the upper range
bound, either of which may be `null`, meaning infinity. Furthermore, a specification whether the bounds are inclusive or
exclusive is given, using either the same string syntax as in PostgreSQL (brackets for inclusive, parentheses for
exclusive), or as two independent `bool` flags. The default is `[)`, i.e., lower bound inclusive, upper bound exclusive.

Compared to PostgreSQL, which canonicalizes equivalent ranges to syntactically same representation, Ivory does not do
any such canonicalization, and ranges are created exactly as specified. Specifically, discrete ranges are *not*
converted to `[)` bounds, as they would conventionally be in PostgreSQL. The method `Range::toBounds()` is available for
explicit conversion, returning a pair of the range equivalent boundary values for a requested bounds specification:
```php
<?php
$range = Range::fromBounds(10, 20, '[]');
assert($range->getLower() === 10);
assert($range->getUpper() === 20);

assert($range->toBounds('[)') === [10, 21]);
assert($range->toBounds('(]') === [9, 20]);
```


#### Empty Ranges

Similarly to PostgreSQL, Ivory recognizes empty ranges. An empty range may be constructed using `Range::empty()` factory
method. However, also the `Range::fromBounds()` may result in an empty range - in case the range boundaries are
specified such that the range is effectively empty (e.g., `[4, 4)` or `(3, 4(`). Like in PostgreSQL, an empty range has
no "position" - the bounds are discarded and just the "empty" flag is maintained for empty ranges.

Operations on empty ranges have the same semantics as in PostgreSQL.


#### Comparability Requirements

As mentioned above, ranges may only be constructed on totally ordered types. In Ivory, it means the range boundary
values must be comparable, which requires an
[`IValueComparator`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Value/Alg/IValueComparator.php)
capable of comparing the values.

Unless specified otherwise (see the next paragraph), the default value comparator, returned by
`Ivory::getDefaultValueComparator()`, is used for all ranges. The comparator is created by the
`ICoreFactory::createDefaultValueComparator()` and cached for the whole script lifetime (or until
`Ivory::flushDefaultValueComparator()` gets called). The standard comparator delivered with Ivory supports:
* PHP base types `int`, `float`, `bool`, `string` and `array`, and
* objects implementing the
   [`IComparable`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Value/Alg/IComparable.php) interface.

Custom comparator may be supplied either:
* globally, using a custom core factory, or
* locally for each individual range by passing the `IValueComparator` to the range factory method.

See the section [Implementing Custom Range Types](#implementing-custom-range-types) below for more details and a
complete example.


#### Discrete Ranges

Ranges on discrete subtypes offer slightly extended functionality. Similarly to
[comparability](#comparability-requirements), this puts certain requirements on the values. Specifically, the type must
implement the notion of the previous and next value. Out of the box, Ivory ranges support PHP integers and integer
strings, and any object implementing the
[`IDiscreteStepper`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Value/Alg/IDiscreteStepper.php)
interface. An implementing example is the `Date` class, representing dates (without time).

The fact a range is treated as discrete affects several things:
* `Range::toBounds()` may be used;
* `Range::equals()` treats equivalent ranges as equal even if they bounds differ;
* `Range::isSinglePoint()` correctly recognizes a single-point ranges, such as `[3, 4)`;
* `Range::fromBounds()` constructs an empty range if both bounds are exclusive and just one step away, such as `(3, 4)`;


### Composite Values

Besides [ranges](#ranges), PostgreSQL allows to create custom composite types. Actually, composites are the most common
data type -- a composite type is
[automatically created](https://www.postgresql.org/docs/11/rowtypes.html#ROWTYPES-DECLARING) with each new table. Ivory,
of course, supports composites out of the box.

In PostgreSQL, composite values are constructed using the `ROW()` construct (with the `ROW` keyword possibly left out),
which actually makes an n-tuple of several values. Only then the anonymous tuple gets typecast (either explicitly or,
e.g., by assigning to a column) to a specific composite type, having named attributes of defined types. Both values of a
defined type and values of the generic type `RECORD` may become a part of a query result.


#### Defined Composite Types

Values of well-defined (stored) composite types are recognized without any problems. Ivory automatically gets
definitions of all the attributes and reads each attribute with the corresponding parser, producing a `Composite` value.
The attributes may be accessed using the object notation:
```php
<?php
$conn->command('CREATE TYPE parse_error AS (file TEXT, line INT, message TEXT)');
$val = $conn->querySingleValue("SELECT ('foo.json', 3, 'Unexpected )')::parse_error");
assert($val instanceof Composite);
assert($val->file === 'foo.json');
assert($val->line === 3);
assert($val->message === 'Unexpected )');
```

The `Composite` class is flexible, so making composite values is very easy:
```php
<?php
$err = Composite::fromMap(['file' => 'bar.c', 'line' => 2]);
$line = $conn->querySingleValue('SELECT (%parse_error).line', $err);
assert($line === 2);
```

If more strictness is demanded, a custom composite type converter and a custom value class must be used. See the
[Implementing Custom Composite Types](#implementing-custom-composite-types) section.


#### Ad Hoc Composites

In case of ad hoc `RECORD` values, there is no type definition Ivory could look up. Unfortunately, there is no way PHP
could get the original attribute types, and there are no attribute names. Hence, instead of using value objects, Ivory
parses `RECORD` values to mere lists of strings:
```php
<?php
$record = $conn->querySingleValue("SELECT ROW('a', -3, 9.81)");
assert($record === ['a', '-3', '9.81']);
```

As for serializing to the `RECORD` type, value-based detection of types is employed, much like for an
[auto-typed placeholder](#placeholder-specification) `%` in SQL patterns:
```php
<?php
$v = $conn->querySingleValue(
	"SELECT %record < (4, 'foo', 3.5)",
	[5e-34, 'bar', 8.9]
);
assert($v === true);
```


### Enumerations

Ivory comes with two flavors of enumeration type converters:
1. a generic one, working for any enumeration out of the box, and
2. a strict one, requiring the user to define an enumeration value class for each enumeration.

In this section, only the first case is documented. The other one requires some extra effort, and it is documented in
section [Implementing Custom Enumeration Types](#implementing-custom-enumeration-types).

Let us define an enumeration type in PostgreSQL, first:
```php
<?php
$conn->command(
    "CREATE TYPE planet AS ENUM (
         'Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune'
     )"
);
```
The Ivory database introspector, which recognizes PostgreSQL types, gets a list of all possible values of the
enumeration and registers a corresponding instance of `EnumType`. Everything is automatic.

When parsing enumeration values from PostgreSQL, `EnumType` produces `EnumItem` objects, which hold the enumeration
value, type, and order of the value within the other enumeration values. `EnumItem` objects are
comparable<sup>[5](#footnote5)</sup>, and even ranges of enum values (of the same enumeration type) are supported:
```php
<?php
$t = $conn->querySingleTuple(
    "SELECT
         'Mars'::planet AS planet1,
         'Mars'::planet AS planet2,
         'Jupiter'::planet AS planet3,
         planet_range('Jupiter', 'Neptune') AS planet_rng"
);
assert($t->planet1 instanceof EnumItem);
assert($t->planet2 instanceof EnumItem);
assert($t->planet3 instanceof EnumItem);
assert($t->planet_rng instanceof Range);

assert($t->planet1->equals($t->planet2));
assert(!$t->planet1->equals($t->planet3));
assert($t->planet1->compareTo($t->planet3) < 0);
assert($t->planet_rng->getLower()->getValue() === 'Jupiter');
```

The generality of `EnumItem` objects comes at a cost, however: to construct an enumeration value, one must use the
`EnumItem::forType()` factory method and give it, besides the actual value, also the schema name, the type name and the
value order. Construction of enumeration values is therefore rather complicated.

To remedy this problem at least when serializing to PostgreSQL, plain strings are accepted besides just `EnumItem`
objects:
```php
<?php
$conn->command('INSERT INTO t (planet) VALUES (%planet)', 'Saturn');
```
Note the given string is checked whether among the values defined by the
enumeration, and a warning is raised if not. Also notice the `EnumItem::getValue()` method, returning the enum value as
a string, which is also returned when simply typecasting an `EnumItem` to a string.

Everything works out of the box without the need to define a PHP class for a specific enumeration type. This also has
the disadvantage that you cannot type-hint the specific type in your functions -- just a generic `EnumItem` may be
declared. However, Ivory also supports the way of defining an enumeration type at the PHP side -- see
[Implementing Custom Enumeration Types](#implementing-custom-enumeration-types) for more on that. 



### Arrays

Ivory fully supports PostgreSQL arrays and is capable of converting arrays of any type between PostgreSQL and PHP.

```php
<?php
$input = ['a', 'b', 'c'];
$output = $conn->querySingleValue('SELECT %s[]', $input);
assert($input === $output);
```

Note, however, that arrays in PHP are quite different beasts than in most of other languages and environments, including
PostgreSQL. Really, the word "array" is rather misleading in PHP -- in fact, the type represents sorted hash maps which
may be used as arrays. For a PHP array to be serializable to PostgreSQL, it must strictly meet the criteria enforced on
PostgreSQL arrays:
* all the values must be of a single data type or `null`;
* multidimensional arrays must be rectangular, i.e., must have the same number of sub-elements under the same keys for
  each element;
* must be indexed by integer keys only, and there may not be any gaps.

Examples of convertible arrays:
* `['a', 'b', 'c']`
* `[ ['a', 'b', 'c'], ['d', null, 'f'] ]`
* `[4 => 'a', 6 => 'c', 5 => 'b']` (the order does not matter -- items will be sorted by keys to
  `[4 => 'a', 5 => 'b', 6 => 'c']`)
* `[ 1 => [7 => 'a', 8 => 'b', 9 => 'c'], 2 => [7 => 'd', 8 => null, 9 => 'f'] ]`

Examples of non-convertible arrays:
* `['a', Date::fromParts(2017, 5, 31), 'c']` (different types)
* `[1 => 'a', 3 => 'b', 5 => 'c']` (non-continuous keys)
* `[ ['a', 'b'], ['c'] ]` (non-rectangular)
* `['a' => 1, 'b' => 2]` (non-integer keys)

Also note that PHP implicitly uses zero-based arrays, while PostgreSQL uses one-based arrays. Nonetheless, both sides
allow for specifying explicit indices in different bounds. By default, Ivory keeps the array indices as is, which means
that a typical array received from PostgreSQL will be one-based:
```php
<?php
$arr = $conn->querySingleValue("SELECT ARRAY['a', 'b', 'c']");
assert($arr === [1 => 'a', 2 => 'b', 3 => 'c']);
```
while sending a typical PHP array to PostgreSQL will result in explicit bounds:
```php
<?php
$conn->command('INSERT INTO t (a) VALUES (%)', ['a', 'b', 'c']);
// SQL sent to database: INSERT INTO t (a) VALUES ('[0:2]={a,b,c}'::pg_catalog.text[])
```

#### Plain Array Mode

For some applications, the strictness about array keys might be unnecessary, or even disturbing. If the only thing you
care about are the values and their mutual order, you can use the _plain_ mode:
* either for arrays of a specific type, using `ArrayType::switchToPlainMode()` on the concrete type converter, or
* for all arrays introspected from the database, using `TypeControl::OPTION_INTROSPECT_PLAIN_ARRAYS`:
```php
<?php
$conn = Ivory::setupNewConnection('host=localhost dbname=mydb');
$conn->setTypeControlOption(\Ivory\Connection\TypeControl::OPTION_INTROSPECT_PLAIN_ARRAYS);
$conn->connect();
```

In the plain mode, keys are completely ignored:
{% highlight php %}
<?php // using plain array mode

$arr = $conn->querySingleValue("SELECT ARRAY['a', 'b', 'c']");
assert($arr === ['a', 'b', 'c']);

$conn->command('INSERT INTO t (a) VALUES (%)', ['a', 'b', 'c']);
// SQL sent to database: INSERT INTO t (a) VALUES (ARRAY['a','b','c']::pg_catalog.text[])
{% endhighlight %}



## Cursors

In PostgreSQL, cursors may either be declared using the SQL `DECLARE` command, or returned from a function as a
`refcursor` value. Ivory supports both:
* the connection, via the `ICursorControl` API, offers the `declareCursor()` method, which is an object envelope of
  the `DECLARE` command, supporting all its possible options like `SCROLL` or `WITH HOLD`;
* `refcursor` values are parsed from `IQueryResult`.

Note that, unless holdable, declaring a cursor needs to occur within a transaction.

```php
<?php
$tx = $this->conn->startTransaction();
$relDef = SqlRelationDefinition::fromSql("VALUES ('a'), ('b'), ('c'), ('d'), ('e')");
$curOne = $this->conn->declareCursor('cur1', $relDef, ICursor::SCROLLABLE);

$this->conn->command(
    <<<'SQL'
    CREATE FUNCTION get_cur() RETURNS refcursor AS $$
    DECLARE
        cur refcursor;
    BEGIN
        OPEN cur FOR VALUES ('x'), ('y'), ('z');
        RETURN cur;
    END;
    $$ LANGUAGE plpgsql
SQL
);
$curTwo = $this->conn->querySingleValue('SELECT get_cur()');
```

Either way, cursors are represented by `ICursor` objects. There are several methods for moving the cursor and fetching
data from it, covering the full operation set offered by PostgreSQL:
* `fetch(int $moveBy = 1): ?ITuple` simply fetches one tuple after moving the cursor relatively to its current position
  by the given number of positions (or returns `null` if it ends up before the first or after the last row);
* `fetchAt(int position): ?ITuple` fetches one tuple from the given absolute position (recall that position 0 means
  before the first row);
* `fetchMulti(int $count): IRelation` fetches multiple rows in one batch, forming a relation on the client side
  (`ICursor::ALL_REMAINING` or `ICursor::ALL_FOREGOING` may be used to fetch all the remaining rows fetching forwards or
  backwards, respectively);
* `moveBy(int $offset): int` just moves the cursor relatively to its current position;
* `moveTo(int $position): int` moves the cursor to the given absolute position; and
* `moveAndCount(int $offset): int` is like `moveBy()` except that it counts the rows passed through.

Note that, for certain operations, PostgreSQL requires the cursor to be _scrollable_. As PostgreSQL automatically marks
a cursor as scrollable if it is simple enough not to cause any extra cost as such, Ivory cannot tell (until asking
PostgreSQL) if a cursor is scrollable or not. Hence, the API does not distinguish scrollable and non-scrollable cursors.
Any operation is, at the API level, allowed on any cursor. Illegal calls will be rejected by PostgreSQL, represented by
a `StatementException` having SQL STATE code `SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE`.

Also note the notion of closing a cursor: once closed, a cursor cannot be used anymore. If Ivory knows the cursor is
closed (especially in case the `ICursor::close()` method is called), a `ClosedCursorException` is thrown from any
operation without even passing the call to PostgreSQL.

As each cursor consumes some resources in PostgreSQL, they are suggested to be closed when not needed.
`ICursor::close()` will close one specific cursor while `IConnection::closeAllCursors()` will close all cursors in the
current session. To list all cursors active in the current session, `IConnection::getAllCursors()` will return an array
of `ICursor`, each representing one of the listed cursors.
```php
<?php
$cursors = $conn->getAllCursors();
$cursors['cur3']->close();
```


### Iterating Cursors

To ease using cursors, `ICursor` extends the `IteratorAggregate` interface. Thus, a cursor may directly be iterated on:
```php
<?php
foreach ($curOne as $pos => $tuple) { // $pos is the absolute position of the row
    assert($tuple instanceof ITuple);
    echo $tuple->value(0);
}
```
Fetching tuples one-by-one will be slow for big data sets, though, as each row is asked by PHP and transferred from
PostgreSQL individually. To help performance, the `getIterator()` method is extended with the optional `int $bufferSize`
parameter. If used, cursor rows will be fetched in batches of the given size, reducing the number of queries. On top of
that, to reduce waiting for the next batch to be computed and transferred to Ivory, the next batch is fetched *in the
background* using [asynchronous queries](#queryasync):
```php
<?php
$bigRelDef = SqlRelationDefinition::fromSql('SELECT generate_series(1, 100000)');
$curThree = $this->conn->declareCursor('cur3', $bigRelDef);
foreach ($curThree->getIterator(1000) as $tuple) { // fetch 1000 rows at once
    assert($tuple instanceof ITuple); // meanwhile, the next 1000 are fetched in the background
    $fetchedValues[] = $tuple->value(0);
}
```



## Transactions

There are special methods for transaction control, so that the user does not have to call the corresponding commands by
hand. Conversely, the user is encouraged to use the API, as other Ivory parts will then work better (e.g., values of
[session variables](#database-session-configuration) are cached, and the cache assumes transactions are controlled using
the API).

There are two kinds of transactions in PostgreSQL: simple and prepared (a.k.a. two-phase commits). Ivory provides API
for both through the `ITransactionControl`, which is a part of the connection.

### Simple Transactions

There is a single entry point: `ITransactionControl::startTransaction()`. Further control, like commit or rollback, is
performed on the [`ITxHandle`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Connection/ITxHandle.php)
object returned from `startTransaction()`. Specifically:
* `ITxHandle::commit()` and `ITxHandle::rollback()` commits and rolls back the transaction;
* a handy `ITxHandle::rollbackIfOpen()` rolls back the transaction, or does nothing if it is already closed, which is
  especially useful for handling exceptions thrown within the transaction in a clean way (i.e., no more
  catch-rollback-rethrow):
```php
<?php
$tx = $conn->startTransaction();
try {
	$conn->command('INSERT INTO t (a) VALUES (4)');
	$conn->command('DELETE FROM w WHERE x = 5');
	$conn->commit();
} finally {
	$conn->rollbackIfOpen(); // effective if an error was raised before commit()
}
```
* `ITxHandle::savepoint()`, `ITxHandle::rollbackToSavepoint()`, and `ITxHandle::releaseSavepoint()` control savepoints
  within the transaction;
* `ITxHandle::prepareTransaction()` prepares the transaction for a [two-phase commit](#prepared-transactions) -- see
  the following section.

To help catching program errors early, the handle keeps track of whether the transaction has been closed through it, and
throws an `InvalidStateException` in case it is attempted to be used further. Additionally, if the transaction handle
gets freed from memory but it has not been used to close the transaction, a warning is raised about the fact (which may
be customized -- see [TxHandle Customization](#txhandle-customization)).
 
Besides explicit transaction control, two interesting features are planned for future releases:
* [#6] nested transactions -- a high-level encapsulation effectively offering nested transactions;
* [#5] auto transactions -- a yet easier transaction processing both for successful and erroneous cases.


#### Transaction Configuration

PostgreSQL [offers several properties](https://www.postgresql.org/docs/11/sql-set-transaction.html) of transactions
which may be configured:
* isolation level,
* access mode (read-only / read+write),
* deferrable mode.

In Ivory, transaction properties are represented by `TxConfig` objects. Those may be passed to special methods which
instruct the database accordingly, using the `SET [SESSION CHARACTERISTICS AS] TRANSACTION` command.

Default options for new transactions, started from now on, are set up using
`ITransactionControl::setupSubsequentTransactions()`:
```php
<?php
$txConfig = TxConfig::create(TxConfig::ISOLATION_SERIALIZABLE);
$conn->setupSubsequentTransactions($txConfig);
```

To affect just a single transaction, you may pass the options directly to the `startTransaction()` method:
```php
<?php
$tx = $conn->startTransaction(TxConfig::ISOLATION_READ_UNCOMMITTED);
// ...
```
...or start the transaction first, and use the `setupTransaction()` on the `ITxHandle`:
```php
<?php
$tx = $conn->startTransaction();
$txConfig = TxConfig::create();
$txConfig->setIsolationLevel(TxConfig::ISOLATION_SERIALIZABLE);
$txConfig->setReadOnly(true);
$txConfig->setDeferrable(true);
$tx->setupTransaction($txConfig); // the transaction can now perform a safe backup
```
> Note that in PostgreSQL, transaction properties may only be set up before making any actual statement within the
> transaction. Trying to set them later leads to a `StatementException`.

To get the actual transaction configuration, use:
* `ITransactionControl::getDefaultTxConfig()`, which returns the options used as defaults for new transactions, and
* `ITxHandle::getTxConfig()`, which returns the options of the current transaction.

> Note that both getters may query the database for the actual values. Considering the PostgreSQL restriction mentioned
> a few lines above, it is _not_ possible to start a transaction, get its options and change it in case they do not fit
> one's needs. The desired transaction properties should be set right away, regardless of their current values.


#### Transaction Snaphots

Ivory also provides a simple wrapper methods for exporting and importing transaction snapshots. Both the
`exportTransactionSnapshot()` and `setTransactionSnapshot()` methods are defined on `ITxHandle` as the snapshots may
only be manipulated during transactions.

The following example demonstrates the effect of importing a snapshot by `$conn2`:
{% highlight php %}
<?php
// to demonstrate snapshots, we need three separate connections
$conn1 = Ivory::setupNewConnection('host=localhost dbname=mydb');
$conn2 = Ivory::setupNewConnection('host=localhost dbname=mydb');
$conn3 = Ivory::setupNewConnection('host=localhost dbname=mydb');

$conn3->command('CREATE TABLE t (i INT)');

$tx1 = $conn1->startTransaction(TxConfig::ISOLATION_REPEATABLE_READ);

assert(0, $conn1->querySingleValue('SELECT COUNT(*) FROM t'));

$conn3->command('INSERT INTO t (i) VALUES (1)');

assert(0, $conn1->querySingleValue('SELECT COUNT(*) FROM t')); // still 0
assert(1, $conn2->querySingleValue('SELECT COUNT(*) FROM t')); // already 1

$snapshotId = $tx1->exportTransactionSnapshot();

$tx2 = $conn2->startTransaction(TxConfig::ISOLATION_REPEATABLE_READ);
$tx2->setTransactionSnapshot($snapshotId); // now, $conn2 sees the same as $conn1

assert(0, $conn2->querySingleValue('SELECT COUNT(*) FROM t')); // still the old state
{% endhighlight %}


### Prepared Transactions

A transaction may be prepared for a two-phase commit using the `ITxHandle::prepareTransaction()`, which must be given a
server-wide unique name, or a random one will automatically be generated. Then, a triplet of methods may be used on a
connection:
* `ITransactionControl::commitPreparedTransaction()` -- commits transaction of the given name;
* `ITransactionControl::rollbackPreparedTransaction()` -- rolls back transaction of the given name;
* `ITransactionControl::listPreparedTransactions()` -- lists all currently prepared transactions.



## Database Session Configuration

The `ISessionControl` part of each connection is very simple. It only contains the `getConfig()` method, which returns
an `IConnConfig` object, representing the runtime configuration. On `IConnConfig`, there is the expected set of methods
for manipulating session variables:
* `get()` returns the value of a configuration parameter;
* `defined()` merely tells whether a parameter is defined;
* `setForSession()` sets a value to hold for the whole session (corresponds to the `SET [SESSION]` SQL command);
* `setForTransaction()` sets a value to hold during the current transaction (corresponds to the `SET LOCAL` SQL
  command).

All the methods take, as their first argument, the configuration parameter name. Standard names, as defined by
PostgreSQL, are defined as constants of `ConfigParam`.

Each configuration parameter in PostgreSQL may be defined as of one of the following types:
* boolean, represented by the PHP `bool` type,
* string, represented by the PHP `string` type,
* numeric, represented by the PHP `int` or `float` type,
* numeric with unit, represented by a `\Ivory\Value\Quantity` object,
* enumerated, represented by the PHP `string` type.

Besides, the special value `IConnConfig::DEFAULT_VALUE` may be passed to setters to reset a configuration parameter to
its default value. All options may be reset at once using `IConnConfig::resetAll()`.

The following sample script illustrates basic usage of the API:
```php
<?php
$tx = $conn->startTransaction();
$cfg = $conn->getConfig();
$cfg->setForTransaction(ConfigParam::STATEMENT_TIMEOUT, Quantity::fromValue(3, Quantity::MINUTE));
$conn->command(...); // some long command
$tx->commit();
echo $cfg->get(ConfigParam::STATEMENT_TIMEOUT); // prints the original value, e.g., "30 s"
```

> Note the configuration values are cached. Thus, only the API, not the SQL `SET` command directly, should be used to
> change configuration options during the database session.

On top of direct manipulation with configuration parameters, `IConnConfig` recognizes the following special getters:
- `getEffectiveSearchPath()` returns the _effective_ `search_path` (i.e., combined with the implicit schemas), as
  [described](https://www.postgresql.org/docs/11/runtime-config-client.html#RUNTIME-CONFIG-CLIENT-STATEMENT) by the
  PostgreSQL manual;
- `getMoneyDecimalSeparator()` returns the decimal separator string used by the `money` data type (which is quite tricky
  to find out, so Ivory provides help here).


### Observable Configuration

By default, `$conn->getConfig()` returns a `ConnConfig` object, which implements `IObservableConnConfig`. It is the
`IConnConfig` interface extended with methods which allow one to observe for configuration changes.

The feature is used by the `ConnConfigValueRetriever` class, which is an `IConfigObserver`. The class serves for reading
configuration values, and keeping the current values cached to prevent repetitive querying the database. By registering
it at the `ConnConfig` object, it gets any subsequent changes of the value, which thus maintains the cached value
automatically.

The `ConnConfigValueRetriever` is useful for data type converters the operation of which depends on runtime
configuration. E.g., [`DateType`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Type/Std/DateType.php)
needs to know the value of `DateStyle`, so that it could parse the dates correctly and tell the `Y-M-D` dates from
`Y-D-M` dates. 



<!--
## Copying Data

TODO #20 once CopyControl gets implemented
STUB: `COPY` command
-->



## Inter-Process Communication

PostgreSQL provides primitives for inter-process communication: the
[`LISTEN`](https://www.postgresql.org/docs/current/sql-listen.html) and
[`NOTIFY`](https://www.postgresql.org/docs/current/sql-notify.html) commands.
Ivory encapsulates the functionality in the
[`IIPCControl`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Connection/IIPCControl.php) interface:
* `listen(string)` and `unlisten(string)` starts/stops listening to a channel;
* `unlistenAll()` stops listening to any channel;
* `notify(string, ?string)` sends a notification to a channel, optionally providing a payload;
* `pollNotification()` polls for a notification;
* `getBackendPID()` finds out the database server process ID.

Unfortunately, the only way for receiving notifications in a PHP script is by polling for it. If there is a notification
in the queue, `pollNotification()` returns it, encapsulated in a
[`Notification`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Connection/Notification.php) object.

The following example shows notifications in action:
{% highlight php %}
<?php
$conn1 = Ivory::setupNewConnection('host=localhost dbname=mydb');
$conn2 = Ivory::setupNewConnection('host=localhost dbname=mydb');

$conn1->listen('c1');
$notification = $conn1->pollNotification();
assert($notification === null); // no notifications yet

$conn2->notify('c1');
$notification = $conn1->pollNotification();
assert($notification->getChannel() === 'c1');

$conn2->notify('c1', 'hello world'); // sending payload
$notification = $conn1->pollNotification();
assert($notification->getPayload() === 'hello world');

// notifications contain the sender PID
assert($conn2->getBackendPID() === $notification->getSenderBackendPID());
{% endhighlight %}



## Caching

There are two crutial areas in which Ivory needs a cache:
* SQL patterns,
* type dictionary.

The SQL patterns, given as strings, are parsed and placeholders are analyzed. The resulting `SqlPattern` object is
cached and reused for identical SQL pattern strings.

As for the type dictionary, the [Data Types](#data-types) chapter already described the automatic introspection of
database types. Despite being optimized, it is a time-consuming process. Thus, the resulting type dictionary is cached
for further use.

> Note that not all data types defined by the database are really cached -- there are hundreds of data types defined in
> a vanilla PostgreSQL database, so Ivory only caches those which have actually been used during the lifetime of the
> script. If new types are used afterwards, the database gets introspected again and the missing types are added to the
> cache. Thus, it is normal that the first few requests are handled with delay. Also, it is advisable to clear the cache
> from time to time to flush outdated items.


### Cache Usage

Ivory itself implements no caching. Instead, it requires the user to supply an appropriate cache implementation.

Any [PSR-6](http://www.php-fig.org/psr/psr-6/) compliant cache pool implementation may be provided. For development,
[file cache](https://packagist.org/packages/cache/filesystem-adapter) might work just fine. In production, however, due
to the nature of cached objects (namely the SQL patterns), it is especially advisable to use shared memory (e.g.,
[`cache/memcached-adapter`](https://packagist.org/packages/cache/memcached-adapter)).

To enable caching, just provide a PSR-6 cache implementation to `Ivory::setDefaultCacheImpl()` _before_ connecting to
the database.

For example, to set up filesystem cache, you may install the `cache/filesystem-adapter` package with:
```
composer require cache/filesystem-adapter
```
and use the following snippet:
```php
<?php
$fsAdapter = new \League\Flysystem\Adapter\Local(sys_get_temp_dir());
$fs = new \League\Flysystem\Filesystem($fsAdapter);
$cachePool = new \Cache\Adapter\Filesystem\FilesystemCachePool($fs);
Ivory::setDefaultCacheImpl($cachePool);
// ...
$conn->connect();
```

As usual, it is possible to define the cache implementation either at the global level, or specifically for a given
connection, calling `ICacheControl::setCacheImpl()`:
```php
<?php
$conn->setCacheImpl($cachePool); // cache pool used just for $conn
```


#### Cache Keys

Please note that the cache keys are constructed such that after upgrading Ivory, old cache entries are ignored and
completely new items are cached. Thus, it may be good to clear the cache after upgrading Ivory.

Moreover, with connection-specific cache, the cache keys also contain identifiers of the connection: host, port and
database name. Thus, it is perfectly safe to use a single cache pool as a connection-specific cache for multiple
connections -- each will use its own cache items.


### ICacheControl

To concentrate all the caching functionality in one place, Ivory defines the `ICacheControl` interface. The default
implementation supplied with Ivory behaves like described above. As with many other parts, the cache control may be
overridden with a custom implementation, or just used differently by the core factory. See the
[Customization](#customization) chapter.



## Customization

Much of the functionality may be customized according to specific needs, as documented by the following subsections.

### The Core Factory

The main entry point for customization is the core factory. For creating various objects, Ivory does not use specific
implementations directly. An `ICoreFactory` object is used for this purpose. By default, `StdCoreFactory` is used,
implemented such that all the standard Ivory features work as documented. Any other implementation may be provided,
though, using `Ivory::setCoreFactory()`:
```php
<?php
Ivory::setCoreFactory(new CustomCoreFactory());
```

Several kinds of objects are created by the core factory:
* `IConnection`, used by `Ivory::setupNewConnection()` (see [Connection Management](#connection-management));
* `TypeRegister`, used for creating the global [type register](#type-registers);
* `ICacheControl`, encapsulating usage of [cache](#caching);
* `ISqlPatternParser`, used for parsing [SQL patterns](#sql-patterns) from strings;
* `StatementExceptionFactory`, used for creating exceptions upon erroneous statements (see
  [Custom Exceptions](#custom-exceptions));
* `ITxHandle`, used by `ITransactionControl::startTransaction()` to represent the transaction (see
  [Simple Transactions](#simple-transactions));
* `IValueComparator`, used by [ranges](#ranges) as the [default value comparator](#comparability-requirements).

Note that many of the objects created by the core factory are kept for reuse once created. Thus, a custom core factory
shall be set up prior to calling any other Ivory methods.


### TxHandle Customization

As described in the [Simple Transactions](#simple-transactions) section, an `ITxHandle` represents an open transaction.
The instances are created by the `ICoreFactory::createTransactionHandle()` factory method. The `StdCoreFactory`
implementation creates a `TxHandle` object, although the factory method may be overridden to return alternative
`ITxHandle` implementations.

One alternative is shipped with Ivory: the `TracingTxHandle` may be helpful during development. It keeps track of where
in the application the transaction handle has been created. Later, if the handle reports a warning that it has not been
closed properly, it includes the stack in the warning message. Thus, it is easy to trace back to where the unclosed
handle has come from.
```php
<?php
Ivory::setCoreFactory(
    new class extends StdCoreFactory
    {
        public function createTransactionHandle(
            IStatementExecution $stmtExec,
            IObservableTransactionControl $observableTxCtl,
            ISessionControl $sessionCtl
        ): ITxHandle {
            return new TracingTxHandle($stmtExec, $observableTxCtl, $sessionCtl);
        }
    }
);
// ...
$tx = $conn->startTransaction();
assert($tx instanceof TracingTxHandle);
unset($tx); // Now the handle gets lost, so it raises a warning, containing stack trace pointing to
            // the $conn->startTransaction() call.
```


### Implementing Custom Data Types

Ivory comes with all standard PostgreSQL data types supported out of the box. For each PostgreSQL type, there is an
implementing type converter within the `Ivory\Type` namespace. PostgreSQL may easily be extended with further data
types, however. In such a case, Ivory may be extended accordingly. The next subsection will cover supporting a new base
type. The following subsections document building composite, enumerated, and range types.


#### Implementing a Custom Base Type

Making support for a custom base type usually consists of three steps:
1. implement the value object class,
2. implement the type converter, and
3. register the type converter with Ivory.

Let's say we want to implement the `ltree` data type of the PostgreSQL
[`ltree` extension](https://www.postgresql.org/docs/current/ltree.html). First, we should decide how to represent the
values in PHP. For label path, an array of strings might be used. However, PostgreSQL has restrictions on which
characters may be used in `ltree` labels. Also, we should define operations on `ltree` values in PHP. Thus, we will
introduce a custom class:
{% highlight php %}
<?php
class Ltree
{
    private $labels;

    public static function fromArray(array $labels)
    {
        foreach ($labels as $label) {
            self::checkLabel($label);
        }
        return new Ltree($labels);
    }

    private static function checkLabel(string $label): void
    {
        if (!preg_match('~ ^ [[:alnum:]_]+ (?: \. [[:alnum:]_]+ )* ~ux', $label)) {
            throw new \InvalidArgumentException('Invalid label used for a label path');
        }
    }

    private function __construct(array $labels)
    {
        $this->labels = $labels;
    }

    public function toArray(): array
    {
        return $this->labels;
    }

    public function join(Ltree $other): Ltree
    {
        return new Ltree(array_merge($this->labels, $other->labels));
    }
}
{% endhighlight %}
Note it is a good practice to specify value objects as immutable, like the class above. Also note the practice of hiding
the constructor, and providing factory methods instead. Such pattern is especially useful when there are multiple kinds
of representations of one object (e.g., a
[`line`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Value/Line.php) may be given either by two
different points, or using coefficients from the `Ax + By + C = 0` equation). Such approach also allows to bypass input
validation in case we already have a valid object, as demonstrated by the `Ltree::join()` method.

Now, when we are able to represent the values, we need to implement the `Ivory\Type\IType` interface, which is fairly
simple: all it needs to do is converting the PHP values from/to the PostgreSQL external representation, and to state its
name so that Ivory may recognize the type within [SQL patterns](#sql-patterns):
{% highlight php %}
<?php
class LtreeType implements \Ivory\Type\IType
{
    public function getSchemaName(): string
    {
        return 'public';
    }

    public function getName(): string
    {
        return 'ltree';
    }

    public function parseValue(string $extRepr)
    {
        $labels = explode('.', $extRepr);
        return Ltree::fromArray($labels);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return ($strictType ? 'NULL::ltree' : 'NULL');
        }

        if ($val instanceof Ltree) {
            $ltree = $val;
        } elseif (is_array($val)) {
            $ltree = Ltree::fromArray($val);
        } else {
            throw new \InvalidArgumentException('Invalid ltree value');
        }

        return ($strictType ? 'ltree ' : '') . "'" . implode('.', $ltree->toArray()) . "'";
    }
}
{% endhighlight %}
The `parseValue()` method is used for converting PostgreSQL data to PHP values. Conversely, `serializeValue()` is called
when serializing PHP values to SQL. It is up to the implementation whether the type converter is strict, or if it
automatically converts the arguments appropriately. Just note the special treatment of `NULL`.
As regards to the name, it is better to parametrize the name of the actual PostgreSQL data type for which the type
converter gets constructed. To save implementing that repeatedly, you may subclass the `Ivory\Type\BaseType` class.

The final step is to register the new type converter with Ivory, either globally or for a selected connection:
```php
<?php
$typeRegister = Ivory::getTypeRegister();
$typeRegister->registerType(new LtreeType());
```

Once the type is registered, its values may be retrieved from the database and serialized within SQL patterns:
```php
<?php
$ltree = $conn->querySingleValue('SELECT %ltree', Ltree::fromArray(['A', 'B', 'C']));
assert($ltree->toArray() === ['A', 'B', 'C']);
```

For some more examples on custom types, see the showcase test
[`TypeSystemTest`](https://github.com/ondrej-bouda/ivory/blob/master/test/unit/Ivory/Showcase/TypeSystemTest.php).


#### Implementing Custom Composite Types

As a general solution, Ivory offers quite a flexible implementation for composite values. Those with a stricter taste
also have an option, however: make custom value classes and adjust the type system to use them. Let us see it all in an
example.

Assume we define a new composite type in Postgres, called `parse_error`:
```sql
CREATE TYPE parse_error AS (
    file TEXT,
    line INT,
    message TEXT
);
```

As already mentioned in the [Defined Composite Types](#defined-composite-types) section, Ivory would parse `parse_error`
values to `Composite` objects. Instead, we define a custom value class:
```php
<?php
/**
 * @property-read string $file
 * @property-read int $line
 * @property-read string|null $message
 */
class ParseError extends StrictComposite
{
    public function __construct(string $file, int $line, ?string $message = null)
    {
        parent::__construct([
            'file' => $file,
            'line' => $line,
            'message' => $message,
        ]);
    }
}
```

The base for a custom composite value is the `StrictComposite` class. That is a simple extension of `Composite`, which
just overrides the attribute getter to raise a warning upon accessing an undefined attribute. The `ParseError` class
itself is then simple: it takes all necessary attributes and passes them to the `Composite::__construct()`. Since the
value objects are immutable, all legitimate attributes must be passed to the parent constructor.

The only missing step is to define and register an explicit converter for the `parse_error` type to use `ParseError`
objects. The generic `CompositeType` offers a handy extension point, due to which this is an easy task:
```php
<?php
class ParseErrorType extends CompositeType
{
    protected function constructCompositeValue(array $valueMap): Composite
    {
        return new ParseError($valueMap['file'], $valueMap['line'], $valueMap['message']);
    }
}

$conn->getTypeRegister()->registerType(new ParseErrorType('public', 'parse_error'));
```

Now, `ParseError` is ready for use:
```php
<?php
$parseError = new ParseError('foo.c', 2, 'Unexpected token: `(`');
$val = $conn->querySingleValue('SELECT %parse_error', $parseError);
assert($val instanceof ParseError);
assert($val->line === 2); // IDE may offer code completion for attributes
assert($val->someAttr === null); // raises a warning due to undefined attribute
```



#### Implementing Custom Enumeration Types

As already described in the [Enumerations](#enumerations) section, Ivory has a generic type converter for any
enumeration types. In this subsection, we will use a more specific way: for each PostgreSQL enumeration type, we define
a PHP counterpart. Such approach offers several advantages over the generic way:
1. the type is recognized within at the PHP side, and may be used for type-hints;
2. values are constructed easily, just by calling pre-defined static methods;
3. values are represented more efficiently.

Let us re-use the example `planet` enumeration:
```php
<?php
$conn->command(
    "CREATE TYPE planet AS ENUM (
         'Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune'
     )"
);
```
Define the `Planet` enumeration class, and register it with Ivory:
```php
<?php
/**
 * @method static Planet Mercury()
 * @method static Planet Venus()
 * @method static Planet Earth()
 * @method static Planet Mars()
 * @method static Planet Jupiter()
 * @method static Planet Saturn()
 * @method static Planet Uranus()
 * @method static Planet Neptune()
 */
class Planet extends \Ivory\Value\StrictEnum
{
    protected static function getValues(): array
    {
        return [
            'Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune',
        ];
    }
}

$typeRegister = $this->conn->getTypeRegister();
$typeRegister->registerType(new StrictEnumType('public', 'planet', Planet::class));
```
Note the `@method` annotations are not strictly necessary -- the code would work just OK without them. They are
beneficial to your IDE for code completion and type information, though.

As annotated, the values may be constructed using the static method call syntax. It is also possible to pass the string
value to the constructor (which checks the value whether among the defined values):
```php
<?php
$mars = new Planet('Mars');
assert($mars == Planet::Mars());
```

Comparison works with the same semantics as in PostgreSQL. For equality, the PHP operators `==` and `!=` may safely be
used, which compare both the enumeration value and type:
```php
<?php
assert(Planet::Mars() == Planet::Mars());
assert(Planet::Mars() != ChocolateBar::Mars());
assert(Planet::Uranus()->compareTo(Planet::Neptune() < 0);

function is_giant(Planet $p): bool
{
    switch ($p) {
        case Planet::Mercury():
        case Planet::Venus():
        case Planet::Earth():
        case Planet::Mars():
            return false;
        case Planet::Jupiter():
        case Planet::Saturn():
        case Planet::Uranus():
        case Planet::Neptune():
            return true;
        default:
            throw new \UnexpectedValueException();
    }
}

assert(is_giant(Planet::Uranus()));
```



#### Implementing Custom Range Types

Range types are supported by means of the
[`Ivory\Type\Postgresql\RangeType`](https://github.com/ondrej-bouda/ivory/blob/master/src/Ivory/Type/Postgresql/RangeType.php)
class. It can work as converter for any type of range if provided with the corresponding subtype converter implementing
the `ITotallyOrderedType`.

For example, to support the PostgreSQL standard `int4range`, the `IntegerType` converter is provided to `RangeType`:
```php
<?php
$typeRegister = Ivory::getTypeRegister();
$intType = new IntegerType('pg_catalog', 'int4');
$typeRegister->registerType(new RangeType('pg_catalog', 'int4range', $intType));
```

Recall that ranges may only be constructed on comparable values (see
[Comparability Requirements](#comparability-requirements) for details). That's why the subtype converter, passed via the
3rd argument to `RangeType::__construct()`, must implement `ITotallyOrderedType`. The interface, extending `IType`,
actually declares no methods -- it serves as a marking interface, so that the implementor of custom ranges is forced to
address the issue. By implementing it, the type converter declares it produces comparable values. Using the default
value comparator, such requirement translates to the values needed to be either PHP base types or objects implementing
`IComparable`. Thus, the easiest way to implement a custom range type is just to make the subtype values implement
`IComparable` and tag the type converter as implementing `ITotallyOrderedType`, and Ivory manages everything
automatically. Iterating on the `Ltree` example [above](#implementing-a-custom-base-type):
```php
<?php
class Ltree implements IComparable
{
    // ...

    use EqualableWithCompareTo; // a helper trait implementing equals() using compareTo()

    public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException();
        }
        if (!$other instanceof LtreeComparable) {
            throw new IncomparableException();
        }

        return ($this->toArray() <=> $other->toArray());
    }
}

class LtreeType implements \Ivory\Type\ITotallyOrderedType
{
    // ...
}

$typeRegister = Ivory::getTypeRegister();
$typeRegister->registerType(new LtreeType());

$ltrees = $conn->querySingleTuple(
	"SELECT ltree 'A.1.b' AS lower, ltree 'A.2.d' AS upper"
);
$range = Range::fromBounds($ltrees->lower, $ltrees->upper);
assert($range->containsElement(LtreeComparable::fromArray(['A', '1', 'f'])) === true);
assert($range->containsElement(LtreeComparable::fromArray(['A', '2', 'd'])) === false);
```


##### Extending the Default Value Comparator

Sometimes, it is not possible to let the value objects implement `IComparable`, such as when we wanted to use PHP
`\DateTime` objects instead of Ivory `Date`. In the [Data Types introduction](#data-types), we presented how to
implement a custom type converter instead of the default one for the `date` type. As a result, one could not use the
`daterange` type due to `\DateTime` objects not being comparable by Ivory. The problem can be fixed by extending the
default value comparator to support `\DateTime`, which in turn can be done by extending the core factory:
```php
<?php
Ivory::setCoreFactory(new class extends StdCoreFactory
{
    public function createDefaultValueComparator(): IValueComparator
    {
        return new class implements IValueComparator
        {
            public function compareValues($a, $b): int
            {
                if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
                    return ($a->getTimestamp() - $b->getTimestamp());
                } else {
                    return ComparisonUtils::compareValues($a, $b);
                }
            }
        };
    }
});
```

To finalize the job, we need to mark the custom date type converter using the `ITotallyOrderedType` interface. That
actually simplifies the implementation, as it may now extend Ivory `DateType` and just adapt its `parseValue()` method
(note that `DateType::serializeValue()` supports `\DateTimeInterface` out of the box, so no effort is on this side):
```php
<?php
$myDateAdapterType = new class('pg_catalog', 'date') extends \Ivory\Type\Std\DateType
{
    public function parseValue(string $extRepr)
    {
        $date = parent::parseValue($extRepr);
        return $date->toDateTime();
    }
};
Ivory::getTypeRegister()->registerType($myDateType);
```


##### Implementing Discrete Range Functionality

As summarized in the [Discrete Ranges](#discrete-ranges) section, it might be important to implement the discrete aspect
of ranges. In order for a custom range type to make discrete ranges, its `parseValue()` method should either return an
`int`, an integer `string`, or an object implementing `IDiscreteStepper`. The rest is managed automatically by `Range`.

For example, the `Date` class supplied with Ivory is an `IDiscreteStepper`:
```php
<?php
class Date extends DateBase implements IDiscreteStepper
{
	// ...
    public function step(int $delta, $value)
    {
        if (!$value instanceof Date) {
            throw new \InvalidArgumentException('$value');
        }
        return $value->addDay($delta);
    }
}
```


##### Implementing Special Range Subclasses

Alternatively to [extending the default value comparator](#extending-the-default-value-comparator) or
[being forced](#implementing-discrete-range-functionality) to let the discrete values implement `IDiscreteStepper`,
there is a finer approach which may be used for absolutely custom ranges: supplying an `IValueComparator` and
`IDiscreteStepper` objects as arguments to `Range::fromBounds()`. Since it would not be practical to pass those to each
new range, the ideal way to do that is by subclassing `Range`.

For demonstration purposes, suppose we have a data type `card` in PostgreSQL for cards from the
[standard 52-card deck](https://en.wikipedia.org/wiki/Standard_52-card_deck). Each card would be represented by a pair
of characters, specifying the rank (`2`, `3`, `4`, `5`, `6`, `7`, `8`, `9`, `T`, `J`, `Q`, `K` or `A`) and color (`C`,
`D`, `H` or `S`). Let us define the order on `card` primarily by the rank, secondarily by the color, and consider a
discrete `cardrange` type on that. The following PHP implementation will do the job:
```php
<?php
class CardRange extends \Ivory\Value\Range
{
    public static function fromBounds(
        $lower,
        $upper,
        $boundsOrLowerInc = '[)',
        ?bool $upperInc = null,
        ?IValueComparator $customComparator = null,
        ?IDiscreteStepper $customDiscreteStepper = null
    ): Range {
        return parent::fromBounds(
            $lower,
            $upper,
            $boundsOrLowerInc,
            $upperInc,
            ($customComparator ?? CardType::provideValueComparator()),
            ($customDiscreteStepper ?? CardType::provideDiscreteStepper())
        );
    }
}

class CardRangeType extends \Ivory\Type\Postgresql\RangeType
{
    protected function createParsedRange($lower, $upper, bool $lowerInc, bool $upperInc): Range
    {
        return CardRange::fromBounds($lower, $upper, $lowerInc, $upperInc);
    }
}

class CardType extends \Ivory\Type\TypeBase implements \Ivory\Type\ITotallyOrderedType
{
    const CARD_ORDER = [
        '2S', '2H', '2D', '2C', '3S', '3H', '3D', '3C', '4S', '4H', '4D', '4C',
        '5S', '5H', '5D', '5C', '6S', '6H', '6D', '6C', '7S', '7H', '7D', '7C',
        '8S', '8H', '8D', '8C', '9S', '9H', '9D', '9C', 'TS', 'TH', 'TD', 'TC',
        'JS', 'JH', 'JD', 'JC', 'QS', 'QH', 'QD', 'QC', 'KS', 'KH', 'KD', 'KC',
        'AS', 'AH', 'AD', 'AC',
    ];

    private static $valueComparator = null;
    private static $discreteStepper = null;

    public static function provideValueComparator(): IValueComparator
    {
        if (self::$valueComparator === null) {
            self::$valueComparator = new class implements IValueComparator
            {
                public function compareValues($a, $b): int
                {
                    if ($a === null || $b === null) {
                        throw new \InvalidArgumentException();
                    }

                    $aPos = array_search($a, CardType::CARD_ORDER);
                    $bPos = array_search($b, CardType::CARD_ORDER);

                    if ($aPos !== false && $bPos !== false) {
                        return ($aPos - $bPos);
                    } else {
                        throw new IncomparableException();
                    }
                }
            };
        }
        return self::$valueComparator;
    }

    public static function provideDiscreteStepper(): IDiscreteStepper
    {
        if (self::$discreteStepper === null) {
            self::$discreteStepper = new class implements IDiscreteStepper
            {
                public function step(int $delta, $value)
                {
                    $pos = array_search($value, CardType::CARD_ORDER);
                    if ($pos === false) {
                        throw new \InvalidArgumentException();
                    }

                    $requestedPos = $pos + $delta;
                    $newPos = max(0, min(count(CardType::CARD_ORDER) - 1, $requestedPos));

                    return CardType::CARD_ORDER[$newPos];
                }
            };
        }
        return self::$discreteStepper;
    }

    public function parseValue(string $extRepr)
    {
        return $extRepr;
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return ($strictType ? 'NULL::card' : 'NULL');
        }
        if (preg_match('~^[23456789TJQKA][CDHS]$~', $val)) {
            return ($strictType ? 'card ' : '') . "'" . $val . "'";
        } else {
            throw new \InvalidArgumentException();
        }
    }
}

// now, everything is defined and ready to be used
$cardType = new CardType('public', 'card');
$cardRangeType = new CardRangeType('public', 'cardrange', $cardType);

$typeRegister = $conn->getTypeRegister();
$typeRegister->registerType($cardType);
$typeRegister->registerType($cardRangeType);

$range = $conn->querySingleValue(
    "SELECT cardrange('7S', NULL) * %cardrange",
    CardRange::fromBounds('3S', 'JS')
);
assert($range instanceof CardRange);
assert($range->toBounds('[]') === ['7S', 'TC']);
```


### Implementing Custom Serializers

Implementing mere custom serializers, like `%like`, is very easy. All you have to do is to implement the trivial
`IValueSerializer` interface and register the serializer with the appropriate type registry. Let us demonstrate that
with the following example.

Ivory is not shipped with any support for making an `IN (...)` construct. We find such SQL code not that useful as it
cannot handle empty lists -- a limitation not applying when using `= ANY(ARRAY[...])` (which, by the way, PostgreSQL
uses to implement the `IN (...)` construct in [some](https://dba.stackexchange.com/a/125500) cases). If, however, it is
useful for you, a custom serializer may save the day:
{% highlight php %}
<?php
$strListSerializer = new class implements IValueSerializer
{
    private $stringType;

    public function __construct()
    {
        $this->stringType = new StringType('%strlist', 'string'); // it requires some name
    }

    public function serializeValue($val): string
    {
        if (!is_array($val)) {
            throw new \InvalidArgumentException('%strlist expects an array');
        }

        $result = '(';
        $isFirst = true;
        foreach ($val as $str) {
            if (!$isFirst) {
                $result .= ', ';
            }
            $isFirst = false;

            $result .= $this->stringType->serializeValue($str);
        }
        if ($isFirst) {
            throw new \InvalidArgumentException('%strlist list cannot be empty');
        }
        $result .= ')';

        return $result;
    }
};
$conn->getTypeRegister()->registerValueSerializer('strlist', $strListSerializer);
// ...
$conn->query('SELECT * FROM person WHERE name IN %strlist', ['John', 'Adam']);
{% endhighlight %}

Note that currently it is difficult to define a generic list serializer, auto-detecting the type of values. Issue
[#19] might improve that.



___

<small>
<a name="footnote1"><sup>1</sup></a>
Actually, injection is still among
[the ten most critical web application security risks](https://www.owasp.org/images/7/72/OWASP_Top_10-2017_%28en%29.pdf.pdf),
according to [OWASP](https://www.owasp.org/).
<br>
<a name="footnote2"><sup>2</sup></a>
This is consistent with PostgreSQL -- e.g., `SELECT 1::"int"` only searches for a user-defined type named "int" within
the `search_path`.
<br>
<a name="footnote3"><sup>3</sup></a>
Realize, for instance, the error message may be translated according to the environment settings of the database server.
<br>
<a name="footnote4"><sup>4</sup></a>
On the other hand, even the `pg_field_type()` function queries the database server in the background, asking for
`pg_type.typname` for the given OID, and caches the result. The type dictionary is a similar mechanism in the userland.
<br>
<a name="footnote5"><sup>5</sup></a>
Recall that in PostgreSQL, enumeration values are compared by their position within the enumeration. The same semantics
is applied by `EnumItem`.
<br>
</small>
