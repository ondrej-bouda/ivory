<?php
namespace Ivory\Showcase;

use Ivory\Data\StatementRelation;



$prepared = new StatementRelation('SELECT * FROM person WHERE id = $*', 42); // "$*" specifies an auto-numbered prepared statement argument
$p42 = $prepared->fetch();


$prepared = new StatementRelation('SELECT length($*), $*d', 'bagr', 333); // "$*d" (or, e.g., "$1d") specifies the data type to tell the database system to use, i.e., the type conversion is not handled by the database layer, but rather the database system

// TODO: decide whether or not to introduce a separate class PreparedStatementRelation
// TODO: decide whether to specify the data type for the PREPARE command this way, or rather let such specification convert the value by the database layer itself, to be consistent
// HINT: data types not specified may be passed to PostgreSQL as "unknown" - PostgreSQL then infers the type
