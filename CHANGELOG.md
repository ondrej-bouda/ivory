# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/)
and this project adheres to [Semantic Versioning](http://semver.org/).

## Unreleased
_no items so far_

## [0.2.1] - 2024-08-06
### Added
- Support [PHP 8.1](https://www.php.net/ChangeLog-8.php#PHP_8_1) (converted pgsql resources to objects)
- Support [PostgreSQL 14+](https://www.postgresql.org/docs/14/release-14.html#id-1.11.6.17.5) (introduced multiranges - these are skipped now, to be implemented in later releases)
### Fixed
- `CursorTest::testCursorTraversable()` and `CursorTest::testBufferedIterator()` to use a scrollable cursor
- `StatementExecutionTest::testStatementException()` for PostgreSQL 16
- `TypeTest::testStatementException()` for PostgresSQL 14 ([removed](https://www.postgresql.org/docs/14/release-14.html#id-1.11.6.17.4) factorial operator `!`)

## [0.2.0] - 2021-04-12
### Added
- Support PHP 8.0
### Fixed
- Fix PHPDoc comments on method varargs
- Fix ConnConfig::get() for case there is a database error when querying for the setting values
### Removed
- Remove support for PHP 7.1

## [0.1.2] - 2020-12-05
### Added
- #5 Automatic transactions
### Fixed
- Fix IntrospectingTypeDictionaryCompiler to fetch all type information in a single query to get a consistent snapshot.

## [0.1.1] - 2020-06-21
### Fixed
- Fix notices on PHP 7.4

## [0.1.0] - 2019-06-05
### Added
- Initial release
