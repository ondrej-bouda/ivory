<?php
namespace Ppg\Type;

/**
 * Manages the correspondence between PostgreSQL types and PHP types.
 */
class TypeRegister
{
	// define mapping for native types
	// define mapping built-in types, recognized in the standard Postgres database
	// recognize the structured types associated with database tables; thus, process e.g. attributes of type "person[]"
	// also recognize anonymous RECORDs, e.g., from query:   WITH a (k,l,m) AS (VALUES (1, 'a', true)) SELECT a FROM a
	// allow the user to define their own types, or even redefine the above ones
}
