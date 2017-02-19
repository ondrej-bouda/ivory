<?php
namespace Ivory;

use PHPUnit\DbUnit\DataSet\AbstractDataSet;
use PHPUnit\DbUnit\DataSet\ITable;

/**
 * Array-based data set for the PHPUnit Database extension.
 *
 * Taken from https://phpunit.de/manual/current/en/database.html
 */
class ArrayDataSet extends AbstractDataSet
{
    /** @var ITable[] map: table name => table representation */
    private $tables = [];

    public function __construct(array $data)
    {
        foreach ($data as $tableName => $rows) {
            $columns = [];
            if (isset($rows[0])) {
                $columns = array_keys($rows[0]);
            }

            $metaData = new \PHPUnit\DbUnit\DataSet\DefaultTableMetadata($tableName, $columns);
            $table = new \PHPUnit\DbUnit\DataSet\DefaultTable($metaData);

            foreach ($rows as $row) {
                $table->addRow($row);
            }
            $this->tables[$tableName] = $table;
        }
    }

    protected function createIterator($reverse = false)
    {
        return new \PHPUnit\DbUnit\DataSet\DefaultTableIterator($this->tables, $reverse);
    }

    public function getTable($tableName)
    {
        if (!isset($this->tables[$tableName])) {
            throw new \InvalidArgumentException("$tableName is not a table in the current database.");
        }

        return $this->tables[$tableName];
    }
}
