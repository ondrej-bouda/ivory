<?php
namespace Ivory\Result;

/**
 * Result of a successful command, i.e., a statement not resulting in a relation, such as `INSERT` or `DELETE`.
 */
interface ICommandResult extends IResult
{
    /**
     * Returns the number of rows affected by the command, if relevant.
     *
     * The affected rows count is relevant for the following commands:
     * - `INSERT`: number of inserted rows;
     * - `UPDATE`: number of updated rows;
     * - `DELETE`: number of deleted rows;
     * - `CREATE TABLE AS`: number of rows inserted right away to the created table;
     * - `MOVE`: number of rows the cursor's position has been changed by;
     * - `COPY`: number of rows copied.
     *
     * @return int|null number of rows affected by the command, or <tt>null</tt> if irrelevant for the command
     */
    function getAffectedRows(): ?int;
}
