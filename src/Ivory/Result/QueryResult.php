<?php
namespace Ivory\Result;

use Ivory\Exception\NotImplementedException;
use Ivory\Exception\ResultException;
use Ivory\Relation\Column;
use Ivory\Relation\RelationMacros;
use Ivory\Type\ITypeDictionary;

class QueryResult extends Result implements IQueryResult
{
	use RelationMacros;

	private $typeDictionary;
	private $columns = null;


	public function __construct($resultHandler, ITypeDictionary $typeDictionary, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);

		$this->typeDictionary = $typeDictionary;

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
			$type = $this->typeDictionary->requireTypeFromOid($typeOid);

			$this->columns[] = new Column($name, $type);
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
