<?php
declare(strict_types=1);
namespace Ivory;

use PHPUnit\DbUnit\DataSet\AbstractDataSet;
use PHPUnit\DbUnit\DataSet\DefaultTable;
use PHPUnit\DbUnit\DataSet\DefaultTableIterator;
use PHPUnit\DbUnit\DataSet\DefaultTableMetadata;
use PHPUnit\DbUnit\DataSet\ITable;
use PHPUnit\DbUnit\DataSet\ITableIterator;

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

            $metaData = new DefaultTableMetadata($tableName, $columns);
            $table = new DefaultTable($metaData);

            foreach ($rows as $row) {
                $table->addRow($row);
            }
            $this->tables[$tableName] = $table;
        }
    }

    protected function createIterator($reverse = false): ITableIterator
    {
        return new DefaultTableIterator($this->tables, $reverse);
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function getTable($tableName): ITable
    {
        if (!isset($this->tables[$tableName])) {
            throw new \InvalidArgumentException("$tableName is not a table in the current database.");
        }

        return $this->tables[$tableName];
    }
}
