<?php
namespace Ivory\Connection;

class CopyControl implements ICopyControl
{
    public function copyFromFile($file, $table, $columns = null, $options = [])
    {

    }

    public function copyFromProgram($program, $table, $columns = null, $options = [])
    {

    }

    public function copyFromInput($table, $columns = null, $options = [])
    {

    }

    public function copyToFile($file, $tableOrQuery, $columns = null, $options = [])
    {

    }

    public function copyToProgram($program, $tableOrQuery, $columns = null, $options = [])
    {

    }

    public function copyToArray($table, $options = [])
    {
        // TODO: besides the actual functionality, remember to fire the corresponding events, just as if the COPY TO STDOUT was executed
    }
}
