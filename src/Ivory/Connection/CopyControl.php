<?php
namespace Ivory\Connection;

use Ivory\Exception\NotImplementedException;
use Ivory\Result\ICommandResult;
use Ivory\Result\ICopyInResult;

class CopyControl implements ICopyControl
{
    public function copyFromFile(string $file, string $table, $columns = null, $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyFromProgram(string $program, string $table, $columns = null, $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyFromInput(string $table, $columns = null, $options = []): ICopyInResult
    {
        throw new NotImplementedException();
    }

    public function copyToFile(string $file, $tableOrRecipe, $columns = null, $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyToProgram(string $program, $tableOrRecipe, $columns = null, $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyToArray(string $table, $options = [])
    {
        // TODO: besides the actual functionality, remember to fire the corresponding events, just as if the COPY TO STDOUT was executed
        throw new NotImplementedException();
    }
}
