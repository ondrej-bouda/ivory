<?php
namespace Ivory\Result;

use Ivory\Exception\NotImplementedException;
use Ivory\Exception\ResultException;
use Ivory\Relation\RelationMacros;

class QueryResult extends Result implements IQueryResult
{
	use RelationMacros;

	private $typeResolver;
	private $columns = null;


	public function __construct($resultHandler, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);

		$this->typeResolver = null; // FIXME

		// TODO: consider uncommenting this to increase performance: the subsequent methods might not always call populate(); on the other hand, explicit flush() would have to be forbidden then...
//		$this->populate(); // not lazy - chances are, when the query was made, the caller will care about its results
	}

    //region ICachingDataProcessor

	public function populate()
	{
		if ($this->columns !== null) {
			return;
		}

		$numFields = pg_num_fields($this->handler);
		if ($numFields < 0 || $numFields === null) {
			throw new ResultException('Error retrieving number of fields of the result.');
		}
		$this->columns = [];
		for ($i = 0; $i < $numFields; $i++) {
			/* NOTE: pg_field_type() cannot be used for simplicity - multiple types of the same name might exist in
			 *       different schemas. Thus, the only reasonable way to recognize the types is using their OIDs,
			 *       returned by pg_field_type_oid(). Up to some extreme cases, within a given database, the same OID
			 *       will always refer to the same data type.
			 */
			$name = pg_field_name($this->handler, $i);
			if ($name === false || $name === null) { // NOTE: besides false, pg_field_name() might return NULL on error
				throw new ResultException("Error retrieving name of result column $i.");
			}
			$typeOid = pg_field_type_oid($this->handler, $i);
			if ($typeOid === false || $typeOid === null) { // NOTE: besides false, pg_field_type_oid() might return NULL on error
				throw new ResultException("Error retrieving type OID of result column $i.");
			}



			/**
			 * Here comes the type recognition problem: we must identify the types of the result columns.
			 * Type names, as returned by pg_field_type(), are insufficient as multiple types of the same name might
			 * exist in different schemas. Thus, the only reasonable way to recognize the types is using their OIDs,
			 * returned by pg_field_type_oid(). Up to some extreme cases, within a given database, the same OID will
			 * always refer the same data type. Once a map of OIDs to the type information is retrieved somehow, the job
			 * is almost done - just use the type loaders to get the right type object.
			 * The only way to get the OID type map is by querying the database. Of course, the result should be cached.
			 * The only interesting part of such caching is finding out whether the cache is stale. The options are:
			 * - putting the responsibility on the user, i.e., if the types change, the user must flush the cache by
			 *   hand;
			 * - automatic versioning of the type system: an integer would be stored in the database, incremented upon
			 *   any DDL query regarding data types - a DDL trigger might be defined to watch this; that would require
			 *   the DDL trigger to be installed in the database, as well as a table/sequence holding the version
			 *   number;
			 *   - this option would be configurable, i.e., the sequence name holding the type system version number
			 *     would be mentioned in the configuration, and if not present in the database, it would be created
			 *     automatically together with the appropriate DDL trigger
			 */
		}
	}

	public function flush()
	{
		$this->columns = null;
	}

	//endregion

	//region IRelation

	public function getColumns()
	{
		$this->populate();
		return $this->columns;
	}

	public function filter($decider)
	{
		throw new NotImplementedException();
	}

	public function project($columns)
	{
		throw new NotImplementedException();
	}

	public function rename($renamePairs)
	{
		throw new NotImplementedException();
	}

	public function col($offsetOrNameOrEvaluator)
	{
		throw new NotImplementedException();
	}

	public function map(...$mappingCols)
	{
		throw new NotImplementedException();
	}

	public function multimap(...$mappingCols)
	{
		throw new NotImplementedException();
	}

	public function assoc(...$cols)
	{
		throw new NotImplementedException();
	}

	public function hash($colOffsetOrNameOrEvaluator, $hasher = null)
	{
		throw new NotImplementedException();
	}

	public function uniq($hasher = null, $comparator = null)
	{
		throw new NotImplementedException();
	}

	public function tuple($offset = 0)
	{
		throw new NotImplementedException();
	}

	//endregion

	//region \Countable

	public function count()
	{
		$numRows = pg_num_rows($this->handler);
		if ($numRows >= 0 && $numRows !== null) { // NOTE: besides -1, pg_num_rows() might return NULL on error
			return $numRows;
		}
		else {
			throw new ResultException('Error retrieving number of rows of the result.');
		}
	}

	//endregion
}
