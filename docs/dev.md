# Developer's Internal Documentation

## Decisions

_Record of decisions made during designing Ivory._

1. Some decisions are mentioned directly in source code as internal comments. Search for the phrase "Ivory design note:"

2. Ivory heavily relies on the type introspector, which looks up all data types defined on the database.
   - The type introspector is necessary to getting type OIDs, besides some other useful metadata (such as array item
     separators).
   - Type OIDs are necessary for identifying data types of relation result columns and processing their data correctly.
     The PHP pgSQL driver only allows recognizing by OID or by an unqualified name. Since there may be multiple data
     types of the same name in different schemas of the database, the latter is insufficient.
     See also the comment for function `php_pgsql_get_data_type` in the `pgsql.c` source file:
     > This is stupid way to do. I'll fix it when I decied how to support user defined types. (Yasuo)

3. The default PHP type into which Ivory will convert SQL `DATE` values is not `\DateTime`, but rather an
   `\Ivory\Value\Date`. The rationale behind this:
   - It is pretty different information whether a variable holds a date or datetime. Just as it is generally better to
     use boolean `true`/`false` values instead of integer `1`/`0`, a date says it is mere date, not time.
   - A lot of issues is connected with datetime values - especially regarding timezones and daylight "saving" time.
     Working with a mere date is much simpler as no such issues are relevant for it.
   - Moreover, the `DATE` type is discrete, while datetime is a continuous type, which may - depending on application -
     be a severe difference.
   - Moreover, `\DateTime::__construct()` does not accept years of more than 4 digits; PostgreSQL may use up to 7 digits
     for years, though, so creating such dates for storage in the database would be complicated and reading them would
     be incorrect.
   - The `\Ivory\Value\Date` has convenient methods to converting from/to `\DateTime`. Thus, it shall be easy to use
     whenever one really needs to get datetime out of a date.
   - After all, Ivory is so flexible that it is extremely easy to change this behaviour: just write a custom `DATE` type
     class and register it either globally or on a per-connection basis.
   - The final reason, common for the decision regarding other temporal types, is that PHP made a poor choice (although
     performance-wise, in those days) when designing the `\DateTime` objects as mutable. `\DateTimeImmutable`, as for
     typing and reading such a horrible name, is quite an unsuccessful try to fix this. (That's not to undermine the
     whole work done for PHP date/time support - in fact, Ivory internally uses `\DateTime` for implementation of its
     own value types. It's just that the interface is not among the best.)

4. Similar reasons hold for using a special `Timestamp` class instead of the built-in DateTime class.

5. As for the own class for representing date/time intervals: the PHP built-in `DateInterval` is out of question as it
   is unable to represent mixed intervals (e.g., `'-1 year -2 mons +3 days -04:05:06'`) - which Postgres might give on
   output. And, by the way, PHP's `DateInterval` only represents the duration, without any reasonable operations on
   them.

6. Coding issue: naturally, strict types declaration does not make sense in pure interface source files. However,
   PhpStorm inspection "Missing strict type declaration" reports such interfaces, and there seems to be no reasonable
   way to mute the inspection on interface source files. It is better to use the inspection at the cost of a little
   nonsense in interface files.

7. Using SQL patterns in production for some time, the `%ident` (and possibly the `%sql`) placeholders have often been
   used for table names, followed by a dot and a column name: `%ident.c`. However, as per the SQL pattern syntax, Ivory
   reads this as to take type `c` from schema `ident` -- which will probably be unintended. Correctly, one should use
   `%{ident}.c`. Switching the parsing rules not to take the dot unless within the braces was considered to prevent such
   mistakes. Finally, the original SQL pattern syntax has been kept due to several reasons:
   - The change would really make a difference for just a few specific type serializers (`ident` and `sql`).
     Although it is rather rare to have types defined in schemas not in the search path, the decision is to support them
     without hassle, just as many other PostgreSQL features not used frequently (which is actually one of the main
     purposes of Ivory -- to support what is unsupported in other database layers).
   - Brackets (as in `int[]`) *are* parsed after the type name, which is a good thing. Not parsing the dot as part of
     the type specifier would make the syntax inconsistent.
   - Taking the dot as part of the type specifier follows the same syntax rules as PostgreSQL has for types. If we use
     braces for something, it should be for separating placeholders from surrounding SQL rather than introducing an
     artificial rule for specifying a data type.
   - It is not a big deal to type `%{ident}.c` once the user gets to know. Detailed messages are used, hinting to use
     the braces, upon errors of this kind.


## Inspections

* Inspection profile _Ivory Inspections_ should pass on scope _Inspection Scope_, including test sources, with no errors
  or warnings. Use the latest PhpStorm for inspecting the code.


## Ideas

* Like transactions, savepoints could also be represented by objects, defining release() and rollback() methods.
  * Quite tough to do the right way, though - savepoint semantics is not trivial. Should the savepoint objects hold
    their state? Then they should link to all subsequence savepoints (which get release upon releasing their previous
    savepoint). Combine with transaction semantics...
* Whenever using type names or other references to pg_catalog object, omit the `pg_catalog` qualification if redundant.
  * I.e., the `pg_catalog` schema is in the search path and no name-conflicting type exists in schemas prior to
    pg_catalog in the search path.
* Revise type dictionary caching - the fact a type has the same OID does not mean it IS the same (it might have been
  altered - composite attributes might have been added/dropped/renamed/changed or new enum item added/renamed; see
  https://www.postgresql.org/docs/current/sql-altertype.html).
  * Consider validating the types upon connection, or instruct the user to flush the dictionary upon any type changes.
  * Consider a helper event trigger, updating a timestamp of last modification of a data type, for a safer solution.


## To Do

* design the interface for write operations
* design the interface for write operations combined with read operations
  * the application takes results from the database, modifies them somehow, and stores them back; that should also work
    for combinations of several relations;
  * use the unique keys known for participating relations.
* consider have the project checked by:
  * https://insight.sensiolabs.com
  * https://codeclimate.com/github/Bee-Lab/bowerphp
* once released publicly, follow existing standards regarding the project maintenance:
  * http://keepachangelog.com
  * http://semver.org/spec/v2.0.0.html
  * look at https://thephpleague.com
* look at Laravel


## Relational Approach

* relation-centric
  * data = relation => getting data = getting relation
  * sources of relations:
    * static objects in database: tables, (materialized) views, set-returning functions
    * static queries = the relation structure (= the list of column names) is static
    * dynamic queries = the relation structure depends on runtime/data


## Type System

* true data types support
  * the result of a query is a data row, which is nothing else than an item of the type `RECORD` - a data type like any other, having nested attributes of other types
  * names and types of a database table columns are just a data type; PostgreSQL has this attitude, too - it generates the corresponding composite data type for each table
* Model classes reflecting database tables are just sources of relations, giving items of a given type
   auto-generate them, including nullable information, comments, etc.
   table (set-returning) functions are also sources of relations, like tables or views; corresponding classes should also be auto-generated for them (also support function aliases)
* see the PostgreSQL object types sketch from the DVN project to get the notion of all data types
  * the sketch only reflects the static types, i.e., those recognized or stored permanently by the database; dynamic runtime types shall be addressed, too
* shortcut for inferring relation data types for expanding further filter conditions: execute the query with FALSE condition, grab the column data types, then use the columns name and type information for serializing input variables correctly as filter conditions; cache the result


## Generator

* generate:
  * data types; mapping to native/auto-generated/recognized PHP types
  * relations for tables, views, table functions
  * relation constants - read from the relation data
* configuration script saying:
  * what kinds of objects to generate
  * what objects to generate (custom filtering possible)
  * where to search (which schemas)
  * how to recognize the relation constants (if ever)
* usage of the whole generator is optional
  * the generated types, and type resolvers, get registered at the static Ivory
  * if there is an unknown type within some results, Ivory creates a generic object on-the-fly (if it is possible)
    * for enum types, it creates an enum object, holding the enum type name and the value string
    * for range types, it creates an appropriate range type object based on the subtype
    * for composite types, it creates a generic composite type object with attributes corresponding to the database type
  * the auto-generated type gets registered so that further processing of this type does not query the database repeatedly


## Relation Features

* easily support trivial CRUD operations; something like active record
* offer the RETURNING variants; e.g., updateReturning() would return a relation
* compound operations; e.g., UPSERT, INSERT IGNORE...
* writable relations:
  * iterate through a relation given by a table or writable view, change some data (even insert or delete whole rows)
    and ultimately call save() on it
  * construct ad-hoc relations from scratch - e.g., for a table or view, or a subset of them, and save them in a batch
  * information about primary/unique keys shall be leveraged


## Re-Use

* There are three kinds of objects:
  * a definition: connection-independent definition of a relation or command;
  * a data source: a definition paired to a database connection, ready to be executed;
  * a result: database result set, either containing the relation data or a description of the command result.


## IDE Support

* plugin for correct syntax highlight of SQL patterns
  * see http://www.jetbrains.org/intellij/sdk/docs/tutorials/custom_language_support_tutorial.html
* investigate whether it is possible to define the number of arguments accepted by a sprintf-like method, apply to query(), command() and similar methods
* static analysis
* ability to systematically recognize all database queries or input yielding a query, and search just within them
* ability to search for all usages of a database object (table, view, column, function, ...); e.g., search for all usages of person.name column
* symbols completion, ideally context-sensitive; especially, names of attributes of queried relations


## Other Layers

* Dibi:
  * http://dibiphp.com/cs/quick-start
  * http://phpfashion.com/temer-v-cili-dibi-0-9b
* Nette Database: http://doc.nette.org/cs/2.3/database
* NotORM: http://www.notorm.com
* Pomm:
  * http://www.pomm-project.org/whatis
  * http://www.pomm-project.org/documentation/manual-1.2
  * https://github.com/chanmix51/elcaro/blob/english/documentation/article.md


## Version-Dependent Aspects

* keywords
* type serializers (\Ivory\Type\Std) and values (\Ivory\Value)
  * the documentation of the classes refer to specific version of PostgreSQL docs -- that might be sufficient to compare
    with and update to the current docs
* ... any feature newly introduced - see the PostgreSQL changelog


## SQL Types

### PostgreSQL built-in types already supported by Ivory

```
Name                        Aliases             Description
====================================================================================================
bigint                      int8                signed eight-byte integer
bigserial                   serial8             autoincrementing eight-byte integer
bit [ (n) ]                                     fixed-length bit string
bit varying [ (n) ]         varbit              variable-length bit string
boolean                     bool                logical Boolean (true/false)
box                                             rectangular box on a plane
bytea                                           binary data ("byte array")
character [ (n) ]           char [ (n) ]        fixed-length character string
character varying [ (n) ]   varchar [ (n) ]     variable-length character string
cidr                                            IPv4 or IPv6 network address
circle                                          circle on a plane
date                                            calendar date (year, month, day)
double precision            float8              double precision floating-point number (8 bytes)
inet                                            IPv4 or IPv6 host address
integer                     int, int4           signed four-byte integer
interval [ fields ] [ (p) ]                     time span
json                                            textual JSON data
jsonb                                           binary JSON data, decomposed
line                                            infinite line on a plane
lseg                                            line segment on a plane
macaddr                                         MAC (Media Access Control) address
macaddr8                                        MAC (Media Access Control) address (EUI-64 format)
money                                           currency amount
numeric [ (p, s) ]          decimal [ (p, s) ]  exact numeric of selectable precision
path                                            geometric path on a plane
pg_lsn                                          PostgreSQL Log Sequence Number
point                                           geometric point on a plane
polygon                                         closed geometric path on a plane
real                        float4              single precision floating-point number (4 bytes)
refcursor                                       cursor declared within a database function
smallint                    int2                signed two-byte integer
smallserial                 serial2             autoincrementing two-byte integer
serial                      serial4             autoincrementing four-byte integer
text                                            variable-length character string
time [ (p) ] [ without time zone ]              time of day (no time zone)
time [ (p) ] with time zone        timetz       time of day, including time zone
timestamp [ (p) ] [ without time zone ]         date and time (no time zone)
timestamp [ (p) ] with time zone   timestamptz  date and time, including time zone
tsquery                                         text search query
tsvector                                        text search document
txid_snapshot                                   user-level transaction ID snapshot
uuid                                            universally unique identifier
xml                                             XML data
```

### PostgreSQL built-in types yet to be supported

```
Name                        Aliases             Description
====================================================================================================
--- (all standard types as of PostgreSQL 11 are supported :-)
```


## SQL Commands

### PostgreSQL commands covered fully

```
ABORT -- abort the current transaction
BEGIN -- start a transaction block
CLOSE -- close a cursor
COMMIT -- commit the current transaction
COMMIT PREPARED -- commit a transaction that was earlier prepared for two-phase commit
DECLARE -- define a cursor
END -- commit the current transaction
FETCH -- retrieve rows from a query using a cursor
LISTEN -- listen for a notification
MOVE -- position a cursor
NOTIFY -- generate a notification
PREPARE TRANSACTION -- prepare the current transaction for two-phase commit
RELEASE SAVEPOINT -- destroy a previously defined savepoint
RESET -- restore the value of a run-time parameter to the default value
ROLLBACK -- abort the current transaction
ROLLBACK PREPARED -- cancel a transaction that was earlier prepared for two-phase commit
ROLLBACK TO SAVEPOINT -- roll back to a savepoint
SAVEPOINT -- define a new savepoint within the current transaction
SET -- change a run-time parameter
SET TRANSACTION -- set the characteristics of the current transaction
SHOW -- show the value of a run-time parameter
START TRANSACTION -- start a transaction block
UNLISTEN -- stop listening for a notification
VALUES -- compute a set of rows
```

### PostgreSQL commands covered partially

_none_

### PostgreSQL commands not covered at all

```
ALTER AGGREGATE -- change the definition of an aggregate function
ALTER COLLATION -- change the definition of a collation
ALTER CONVERSION -- change the definition of a conversion
ALTER DATABASE -- change a database
ALTER DEFAULT PRIVILEGES -- define default access privileges
ALTER DOMAIN --  change the definition of a domain
ALTER EVENT TRIGGER -- change the definition of an event trigger
ALTER EXTENSION --  change the definition of an extension
ALTER FOREIGN DATA WRAPPER -- change the definition of a foreign-data wrapper
ALTER FOREIGN TABLE -- change the definition of a foreign table
ALTER FUNCTION -- change the definition of a function
ALTER GROUP -- change role name or membership
ALTER INDEX -- change the definition of an index
ALTER LANGUAGE -- change the definition of a procedural language
ALTER LARGE OBJECT -- change the definition of a large object
ALTER MATERIALIZED VIEW -- change the definition of a materialized view
ALTER OPERATOR -- change the definition of an operator
ALTER OPERATOR CLASS -- change the definition of an operator class
ALTER OPERATOR FAMILY -- change the definition of an operator family
ALTER POLICY — change the definition of a row level security policy
ALTER PROCEDURE — change the definition of a procedure
ALTER PUBLICATION — change the definition of a publication 
ALTER ROLE -- change a database role
ALTER ROUTINE — change the definition of a routine
ALTER RULE -- change the definition of a rule
ALTER SCHEMA -- change the definition of a schema
ALTER SEQUENCE --  change the definition of a sequence generator
ALTER SERVER -- change the definition of a foreign server
ALTER STATISTICS — change the definition of an extended statistics object
ALTER SUBSCRIPTION — change the definition of a subscription
ALTER SYSTEM -- change a server configuration parameter
ALTER TABLE -- change the definition of a table
ALTER TABLESPACE -- change the definition of a tablespace
ALTER TEXT SEARCH CONFIGURATION -- change the definition of a text search configuration
ALTER TEXT SEARCH DICTIONARY -- change the definition of a text search dictionary
ALTER TEXT SEARCH PARSER -- change the definition of a text search parser
ALTER TEXT SEARCH TEMPLATE -- change the definition of a text search template
ALTER TRIGGER -- change the definition of a trigger
ALTER TYPE --  change the definition of a type
ALTER USER -- change a database role
ALTER USER MAPPING -- change the definition of a user mapping
ALTER VIEW -- change the definition of a view
ANALYZE -- collect statistics about a database
CALL — invoke a procedure
CHECKPOINT -- force a transaction log checkpoint
CLUSTER -- cluster a table according to an index
COMMENT -- define or change the comment of an object
COPY -- copy data between a file and a table
CREATE ACCESS METHOD — define a new access method
CREATE AGGREGATE -- define a new aggregate function
CREATE CAST -- define a new cast
CREATE COLLATION -- define a new collation
CREATE CONVERSION -- define a new encoding conversion
CREATE DATABASE -- create a new database
CREATE DOMAIN -- define a new domain
CREATE EVENT TRIGGER -- define a new event trigger
CREATE EXTENSION -- install an extension
CREATE FOREIGN DATA WRAPPER -- define a new foreign-data wrapper
CREATE FOREIGN TABLE -- define a new foreign table
CREATE FUNCTION -- define a new function
CREATE GROUP -- define a new database role
CREATE INDEX -- define a new index
CREATE LANGUAGE -- define a new procedural language
CREATE MATERIALIZED VIEW -- define a new materialized view
CREATE OPERATOR -- define a new operator
CREATE OPERATOR CLASS -- define a new operator class
CREATE OPERATOR FAMILY -- define a new operator family
CREATE POLICY — define a new row level security policy for a table
CREATE PROCEDURE — define a new procedure
CREATE PUBLICATION — define a new publication
CREATE ROLE -- define a new database role
CREATE RULE -- define a new rewrite rule
CREATE SCHEMA -- define a new schema
CREATE SEQUENCE -- define a new sequence generator
CREATE SERVER -- define a new foreign server
CREATE STATISTICS — define extended statistics
CREATE SUBSCRIPTION — define a new subscription
CREATE TABLE -- define a new table
CREATE TABLE AS -- define a new table from the results of a query
CREATE TABLESPACE -- define a new tablespace
CREATE TEXT SEARCH CONFIGURATION -- define a new text search configuration
CREATE TEXT SEARCH DICTIONARY -- define a new text search dictionary
CREATE TEXT SEARCH PARSER -- define a new text search parser
CREATE TEXT SEARCH TEMPLATE -- define a new text search template
CREATE TRANSFORM — define a new transform
CREATE TRIGGER -- define a new trigger
CREATE TYPE -- define a new data type
CREATE USER -- define a new database role
CREATE USER MAPPING -- define a new mapping of a user to a foreign server
CREATE VIEW -- define a new view
DEALLOCATE -- deallocate a prepared statement
DELETE -- delete rows of a table
DISCARD -- discard session state
DO -- execute an anonymous code block
DROP ACCESS METHOD — remove an access method
DROP AGGREGATE -- remove an aggregate function
DROP CAST -- remove a cast
DROP COLLATION -- remove a collation
DROP CONVERSION -- remove a conversion
DROP DATABASE -- remove a database
DROP DOMAIN -- remove a domain
DROP EVENT TRIGGER -- remove an event trigger
DROP EXTENSION -- remove an extension
DROP FOREIGN DATA WRAPPER -- remove a foreign-data wrapper
DROP FOREIGN TABLE -- remove a foreign table
DROP FUNCTION -- remove a function
DROP GROUP -- remove a database role
DROP INDEX -- remove an index
DROP LANGUAGE -- remove a procedural language
DROP MATERIALIZED VIEW -- remove a materialized view
DROP OPERATOR -- remove an operator
DROP OPERATOR CLASS -- remove an operator class
DROP OPERATOR FAMILY -- remove an operator family
DROP OWNED -- remove database objects owned by a database role
DROP POLICY — remove a row level security policy from a table
DROP PROCEDURE — remove a procedure
DROP PUBLICATION — remove a publication
DROP ROLE -- remove a database role
DROP ROUTINE — remove a routine
DROP RULE -- remove a rewrite rule
DROP SCHEMA -- remove a schema
DROP SEQUENCE -- remove a sequence
DROP SERVER -- remove a foreign server descriptor
DROP STATISTICS — remove extended statistics
DROP SUBSCRIPTION — remove a subscription
DROP TABLE -- remove a table
DROP TABLESPACE -- remove a tablespace
DROP TEXT SEARCH CONFIGURATION -- remove a text search configuration
DROP TEXT SEARCH DICTIONARY -- remove a text search dictionary
DROP TEXT SEARCH PARSER -- remove a text search parser
DROP TEXT SEARCH TEMPLATE -- remove a text search template
DROP TRANSFORM — remove a transform
DROP TRIGGER -- remove a trigger
DROP TYPE -- remove a data type
DROP USER -- remove a database role
DROP USER MAPPING -- remove a user mapping for a foreign server
DROP VIEW -- remove a view
EXECUTE -- execute a prepared statement
EXPLAIN -- show the execution plan of a statement
GRANT -- define access privileges
IMPORT FOREIGN SCHEMA — import table definitions from a foreign server
INSERT -- create new rows in a table
LOAD -- load a shared library file
LOCK -- lock a table
PREPARE -- prepare a statement for execution
REASSIGN OWNED -- change the ownership of database objects owned by a database role
REFRESH MATERIALIZED VIEW -- replace the contents of a materialized view
REINDEX -- rebuild indexes
REVOKE -- remove access privileges
SECURITY LABEL -- define or change a security label applied to an object
SELECT -- retrieve rows from a table or view
SELECT INTO -- define a new table from the results of a query
SET CONSTRAINTS -- set constraint check timing for the current transaction
SET ROLE -- set the current user identifier of the current session
SET SESSION AUTHORIZATION -- set the session user identifier and the current user identifier of the current session
TRUNCATE -- empty a table or set of tables
UPDATE -- update rows of a table
VACUUM -- garbage-collect and optionally analyze a database
```


## PostgreSQL Command Tags

```
COPY: COPY <count>
    <count>: number of rows copied

DELETE: DELETE <count>
    <count>: number of rows actually deleted

FETCH: FETCH <count>
    <count>: number of rows fetched

INSERT: INSERT <oid> <count>
    <count>: number of rows inserted
    <oid>: the OID assigned to the inserted row if <count> is exactly one, and the target table has OIDs, zero otherwise

MOVE: MOVE <count>
    <count>: number of rows that a FETCH command with the same parameters would have returned

SELECT: SELECT <count>
    <count>: number of rows retrieved

CREATE TABLE AS: SELECT <count>
    <count>: number of rows retrieved

UPDATE: UPDATE <count>
    <count>: number of rows updated, including matched rows whose values did not change

[other command]: [other command]
```

