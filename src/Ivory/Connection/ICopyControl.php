<?php
namespace Ivory\Connection;

use Ivory\Query\IRelationRecipe;
use Ivory\Result\ICommandResult;
use Ivory\Result\ICopyInResult;

/**
 * Control over copying data from/to a PostgreSQL database, using the `COPY` command.
 *
 * @see http://www.postgresql.org/docs/9.4/static/sql-copy.html
 */
interface ICopyControl
{
    /**
     * Takes `string`: name of the data format to be read or written; constants `FORMAT_*` may be used. Defaults to
     * {@link ICopyControl::FORMAT_TEXT}.
     */
    const FORMAT = 'format';
    /** Takes `bool`: whether the OID is to be copied for each row. Defaults to `false`. */
    const OIDS = 'oids';
    /** Takes `bool`: whether the data is to be copied with rows already frozen. Defaults to `false`. */
    const FREEZE = 'freeze';
    /**
     * Takes `string`: character separating columns within each row. May only be a single one-byte character.
     * - In the {@link ICopyControl::FORMAT_TEXT} format, the default is a tab character.
     * - In the {@link ICopyControl::FORMAT_CSV} format, the default is a comma.
     * - In the {@link ICopyControl::FORMAT_BINARY} format, this option is not allowed.
     */
    const DELIMITER = 'delimiter';
    /**
     * Takes `string`: string representing a `NULL` value.
     * - In the {@link ICopyControl::FORMAT_TEXT} format, the default is `'\N'` (backslash and capital N).
     * - In the {@link ICopyControl::FORMAT_CSV} format, the default is an unquoted empty string.
     * - In the {@link ICopyControl::FORMAT_BINARY} format, this option is not allowed.
     */
    const NULL = 'null';
    /**
     * Takes `bool`: whether the data contain a header line with the names of each column in the data.
     *
     * Only allowed for operations using format {@link ICopyControl::FORMAT_CSV}. Defaults to `false`.
     */
    const HEADER = 'header';
    /**
     * Takes `string`: value quoting character. May only be a single one-byte character.
     *
     * Only allowed for operations using format {@link ICopyControl::FORMAT_CSV}. Defaults to `'"'` (double-quote).
     */
    const QUOTE = 'quote';
    /**
     * Takes `string`: escape character for the {@link ICopyControl::QUOTE} character.
     * May only be a single one-byte character.
     *
     * Only allowed for operations using format {@link ICopyControl::FORMAT_CSV}. Defaults to the `QUOTE` character.
     */
    const ESCAPE = 'escape';
    /**
     * Takes `string[]|string`: list of names of columns the non-null values of which are to be forcibly quoted, or
     * `'*'` for all the table columns.
     *
     * Only allowed for `copyTo*` operations using format {@link ICopyControl::FORMAT_CSV}.
     */
    const FORCE_QUOTE = 'force_quote';
    /**
     * Takes `string[]`: list of names of columns the values of which are not to be matched against the null string.
     *
     * Only allowed for `copyFrom*` operations using format {@link ICopyControl::FORMAT_CSV}.
     */
    const FORCE_NOT_NULL = 'force_not_null';
    /**
     * Takes `string[]`: list of names of columns the values of which are to be matched against the null string even if
     * they have been quoted.
     *
     * Only allowed for `copyFrom*` operations using format {@link ICopyControl::FORMAT_CSV}.
     */
    const FORCE_NULL = 'force_null';
    /** Takes `string`: name of encoding used for reading/writing data. Defaults to the current client encoding. */
    const ENCODING = 'encoding';

    /** The text format. To be used as a value for the {@link ICopyControl::FORMAT} option. */
    const FORMAT_TEXT = 'text';
    /** The comma-separated values format. To be used as a value for the {@link ICopyControl::FORMAT} option. */
    const FORMAT_CSV = 'csv';
    /** The binary format. To be used as a value for the {@link ICopyControl::FORMAT} option. */
    const FORMAT_BINARY = 'binary';


    /**
     * Instructs the database server to copy contents of a file to a database table.
     *
     * The file must be readable by the PostgreSQL user.
     *
     * After this method returns, the connection is free to accept any more statements.
     *
     * @param string $file path name of the input file;
     *                     relative or absolute path interpreted from the viewpoint of the database server
     * @param string $table name of the database table to copy the data to; might be schema-qualified
     * @param string[]|null $columns list of names of columns values of which are expected in the given file;
     *                               skipping with <tt>null</tt> yields all the table columns in their definition order
     * @param array $options map of options for <tt>COPY</tt>: option (one of {@link ICopyControl} constants) => value;
     *                       see the constants on what values are accepted for which options
     * @return ICommandResult the result of the `COPY` command
     */
    function copyFromFile(string $file, string $table, $columns = null, $options = []): ICommandResult;

    /**
     * Instructs the database server to executes an external program and copy its output to a database table.
     *
     * The program must be executable by the PostgreSQL user.
     *
     * After this method returns, the connection is free to accept any more statements.
     *
     * @param string $program command executed by the database server, the standard output of which will get copied to
     *                          the database table
     * @param string $table name of the database table to copy the data to; might be schema-qualified
     * @param string[]|null $columns list of names of columns values of which will be given by the program;
     *                               skipping with <tt>null</tt> yields all the table columns in their definition order
     * @param array $options map of options for <tt>COPY</tt>: option (one of {@link ICopyControl} constants) => value;
     *                       see the constants on what values are accepted for which options
     * @return ICommandResult the result of the `COPY` command
     */
    function copyFromProgram(string $program, string $table, $columns = null, $options = []): ICommandResult;

    /**
     * Initiates copying data to a database table.
     *
     * The returned {@link ICopyInResult} object is to be used to pass the actual data and finalize copying.
     *
     * Until the {@link ICopyInResult::end()} method is called on the returned object, the connection is blocked.
     *
     * @param string $table name of the database table to copy the data to; might be schema-qualified
     * @param string[]|null $columns list of names of columns values of which will be given on the input;
     *                               skipping with <tt>null</tt> yields all the table columns in their definition order
     * @param array $options map of options for <tt>COPY</tt>: option (one of {@link ICopyControl} constants) => value;
     *                       see the constants on what values are accepted for which options
     * @return ICopyInResult the initiated `COPY` command result
     */
    function copyFromInput(string $table, $columns = null, $options = []): ICopyInResult;

    /**
     * Instructs the database server to copy a database table or query result to a file.
     *
     * The file must be writable by the PostgreSQL user.
     *
     * After this method returns, the connection is free to accept further statements.
     *
     * @param string $file path name of the output file;
     *                     relative or absolute path interpreted from the viewpoint of the database server
     * @param string|IRelationRecipe $tableOrRecipe either an (optionally schema-qualified) table name or recipe to
     *                                                relation giving the data to be copied
     * @param string[]|null $columns list of names of columns values of which will be copied to the given file;
     *                               skipping with <tt>null</tt> yields all the table columns in their definition order
     * @param array $options map of options for <tt>COPY</tt>: option (one of {@link ICopyControl} constants) => value;
     *                       see the constants on what values are accepted for which options
     * @return ICommandResult the result of the `COPY` command
     */
    function copyToFile(string $file, $tableOrRecipe, $columns = null, $options = []): ICommandResult;

    /**
     * Instructs the database server to copy a database table or query result to the input of a program.
     *
     * The program must be executable by the PostgreSQL user.
     *
     * After this method returns, the connection is free to accept further statements.
     *
     * @param string $program command executed by the database server, the standard input of which will get fed with
     *                          the table or query result data
     * @param string|IRelationRecipe $tableOrRecipe either an (optionally schema-qualified) table name or recipe to
     *                                                relation giving the data to be copied
     * @param string[]|null $columns list of names of columns values of which will be given to the program;
     *                               skipping with <tt>null</tt> yields all the table columns in their definition order
     * @param array $options map of options for <tt>COPY</tt>: option (one of {@link ICopyControl} constants) => value;
     *                       see the constants on what values are accepted for which options
     * @return ICommandResult the result of the `COPY` command
     */
    function copyToProgram(string $program, $tableOrRecipe, $columns = null, $options = []): ICommandResult;

    /**
     * Copies a database table to an array of data rows, using the {@link ICopyControl::FORMAT_TEXT `TEXT`} format.
     *
     * After this method returns, the connection is free to accept any more statements.
     *
     * Compared to other copy methods, the interface of this method is rather limited due to the fact it calls the PHP
     * {@link pg_copy_to()} function internally, which is the only code in the `pgsql` extension calling `PQgetline()`,
     * as of PHP 7.0.
     *
     * @param string $table table name to copy, optionally schema-qualified
     * @param array $options map of options for <tt>COPY</tt>: option (one of {@link ICopyControl} constants) => value;
     *                       only {@link ICopyControl::DELIMITER} and {@link ICopyControl::NULL} may be used
     * @return string[] lines representing individual rows in the {@link ICopyControl::FORMAT_TEXT `TEXT`} format, each
     *                    including the trailing newline character
     */
    function copyToArray(string $table, $options = []);
}
