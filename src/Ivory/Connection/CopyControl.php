<?php
namespace Ivory\Connection;

use Ivory\Exception\NotImplementedException;
use Ivory\Result\ICommandResult;
use Ivory\Result\ICopyInResult;

class CopyControl implements ICopyControl
{
    public function copyFromFile(string $file, string $table, ?array $columns = null, array $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyFromProgram(string $program, string $table, ?array $columns = null, array $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyFromInput(string $table, ?array $columns = null, array $options = []): ICopyInResult
    {
        throw new NotImplementedException();
    }

    public function copyToFile(string $file, $tableOrRelationDefinition, ?array $columns = null, array $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyToProgram(string $program, $tableOrRelationDefinition, ?array $columns = null, array $options = []): ICommandResult
    {
        throw new NotImplementedException();
    }

    public function copyToArray(string $table, array $options = []): array
    {
        // TODO: besides the actual functionality, remember to fire the corresponding events, just as if the COPY TO STDOUT was executed
        throw new NotImplementedException();
    }
}
