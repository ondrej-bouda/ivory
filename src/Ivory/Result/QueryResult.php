<?php
namespace Ivory\Result;

use Ivory\Exception\NotImplementedException;
use Ivory\Exception\ResultException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Relation\Column;
use Ivory\Relation\RelationMacros;
use Ivory\Relation\Tuple;
use Ivory\Type\ITypeDictionary;

class QueryResult extends Result implements \Iterator, IQueryResult
{
	use RelationMacros;

	private $typeDictionary;
	private $pos = 0;
	private $numRows;
	private $populated = false;
	/** @var Column[] */
	private $columns;
	/** @var int[] map: column name => offset of the first column of the name */
	private $colNameMap;


	/**
	 * @param resource $resultHandler the result, with the internal pointer at the beginning
	 * @param ITypeDictionary $typeDictionary
	 * @param string|null $lastNotice last notice captured on the connection
	 */
	public function __construct($resultHandler, ITypeDictionary $typeDictionary, $lastNotice = null)
	{
		parent::__construct($resultHandler, $lastNotice);

		$this->typeDictionary = $typeDictionary;

		$this->numRows = $this->fetchNumRows();
		$this->populate(); // not lazy - chances are, when the query was made, the caller will care about its results
	}

	private function fetchNumRows()
	{
		$numRows = pg_num_rows($this->handler);
		if ($numRows >= 0 && $numRows !== null) { // NOTE: besides -1, pg_num_rows() might return NULL on error
			return $numRows;
		}
		else {
			throw new ResultException('Error retrieving number of rows of the result.');
		}
	}


    //region ICachingDataProcessor

	public function populate()
	{
		if ($this->populated) {
			return;
		}

		$numFields = pg_num_fields($this->handler);
		if ($numFields < 0 || $numFields === null) {
			throw new ResultException('Error retrieving number of fields of the result.');
		}
		$this->columns = [];
		$this->colNameMap = [];
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
			if ($name == '?column?') {
				$name = null;
			}
			$typeOid = pg_field_type_oid($this->handler, $i);
			if ($typeOid === false || $typeOid === null) { // NOTE: besides false, pg_field_type_oid() might return NULL on error
				throw new ResultException("Error retrieving type OID of result column $i.");
			}
			$type = $this->typeDictionary->requireTypeFromOid($typeOid);

			$this->columns[] = new Column($this, $i, $name, $type);

			if ($name !== null && !isset($this->colNameMap[$name])) {
				$this->colNameMap[$name] = $i;
			}
		}

		$this->populated = true;
	}

	public function flush()
	{
		$this->populated = false;
		$this->populate(); // re-initialize the internal data right away for the other methods not to call populate() over and over again
	}

	//endregion

	//region IRelation

	public function getColumns()
	{
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
		if (is_scalar($offsetOrNameOrEvaluator)) {
			if (filter_var($offsetOrNameOrEvaluator, FILTER_VALIDATE_INT) !== false) {
				if (isset($this->columns[$offsetOrNameOrEvaluator])) {
					return $this->columns[$offsetOrNameOrEvaluator];
				}
				else {
					throw new UndefinedColumnException("No column at offset $offsetOrNameOrEvaluator");
				}
			}
			else {
				if (isset($this->colNameMap[$offsetOrNameOrEvaluator])) {
					return $this->columns[$this->colNameMap[$offsetOrNameOrEvaluator]];
				}
				else {
					throw new UndefinedColumnException("No column named $offsetOrNameOrEvaluator");
				}
			}
		}
		elseif ($offsetOrNameOrEvaluator instanceof ITupleEvaluator ||
		  		$offsetOrNameOrEvaluator instanceof \Closure)
		{
			return new Column($this, $offsetOrNameOrEvaluator, null, null);
		}
		else {
			throw new \InvalidArgumentException('$offsetOrNameOrEvaluator');
		}
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
		if ($offset >= $this->numRows) {
			throw new \OutOfBoundsException("Offset $offset is out of the result bounds [0,{$this->numRows})");
		}

		$rawData = pg_fetch_row($this->handler, $offset);
		if ($rawData === false || $rawData === null) {
			throw new ResultException("Error fetching row at offset $offset");
		}

		$data = [];
		foreach ($this->columns as $i => $col) {
			$data[$i] = $col->getType()->parseValue($rawData[$i]);
		}

		return new Tuple($data, $this->columns, $this->colNameMap);
	}

	//endregion

	//region \Countable

	public function count()
	{
		return $this->numRows;
	}

	//endregion

	//region \Iterator

	public function current()
	{
		return $this->tuple($this->pos);
	}

	public function next()
	{
		$this->pos++;
	}

	public function key()
	{
		return $this->pos;
	}

	public function valid()
	{
		return ($this->pos < $this->numRows);
	}

	public function rewind()
	{
		$this->pos = 0;
	}

	//endregion
}
