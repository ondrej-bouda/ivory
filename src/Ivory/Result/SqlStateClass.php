<?php
namespace Ivory\Result;

/**
 * Classes of SQL STATE codes.
 *
 * @see SqlState
 * @see http://www.postgresql.org/docs/9.4/static/errcodes-appendix.html
 */
class SqlStateClass
{
    const SUCCESSFUL_COMPLETION = '00';
    const WARNING = '01';
    /** No Data (this is also a warning class per the SQL standard) */
    const NO_DATA = '02';
    const SQL_STATEMENT_NOT_YET_COMPLETE = '03';
    const CONNECTION_EXCEPTION = '08';
    const TRIGGERED_ACTION_EXCEPTION = '09';
    const FEATURE_NOT_SUPPORTED = '0A';
    const INVALID_TRANSACTION_INITIATION = '0B';
    const LOCATOR_EXCEPTION = '0F';
    const INVALID_GRANTOR = '0L';
    const INVALID_ROLE_SPECIFICATION = '0P';
    const DIAGNOSTICS_EXCEPTION = '0Z';
    const CASE_NOT_FOUND = '20';
    const CARDINALITY_VIOLATION = '21';
    const DATA_EXCEPTION = '22';
    const INTEGRITY_CONSTRAINT_VIOLATION = '23';
    const INVALID_CURSOR_STATE = '24';
    const INVALID_TRANSACTION_STATE = '25';
    const INVALID_SQL_STATEMENT_NAME = '26';
    const TRIGGERED_DATA_CHANGE_VIOLATION = '27';
    const INVALID_AUTHORIZATION_SPECIFICATION = '28';
    const DEPENDENT_PRIVILEGE_DESCRIPTORS_STILL_EXIST = '2B';
    const INVALID_TRANSACTION_TERMINATION = '2D';
    const SQL_ROUTINE_EXCEPTION = '2F';
    const INVALID_CURSOR_NAME = '34';
    const EXTERNAL_ROUTINE_EXCEPTION = '38';
    const EXTERNAL_ROUTINE_INVOCATION_EXCEPTION = '39';
    const SAVEPOINT_EXCEPTION = '3B';
    const INVALID_CATALOG_NAME = '3D';
    const INVALID_SCHEMA_NAME = '3F';
    const TRANSACTION_ROLLBACK = '40';
    const SYNTAX_ERROR_OR_ACCESS_RULE_VIOLATION = '42';
    const WITH_CHECK_OPTION_VIOLATION = '44';
    const INSUFFICIENT_RESOURCES = '53';
    const PROGRAM_LIMIT_EXCEEDED = '54';
    const OBJECT_NOT_IN_PREREQUISITE_STATE = '55';
    const OPERATOR_INTERVENTION = '57';
    /* System Error (errors external to PostgreSQL itself) */
    const SYSTEM_ERROR = '58';
    const CONFIG_FILE_ERROR = 'F0';
    /* Foreign Data Wrapper Error (SQL/MED) */
    const FDW_ERROR = 'HV';
    /* PL/pgSQL Error */
    const PLPGSQL_ERROR = 'P0';
    const INTERNAL_ERROR = 'XX';
}
