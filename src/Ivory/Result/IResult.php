<?php
namespace Ivory\Result;

/**
 * Result of a successful statement.
 */
interface IResult
{
    /**
     * Returns the last notice emitted for this result.
     *
     * Note some notices might be swallowed - see the notes below. A notice is returned by this method only if it is
     * sure it was emitted for this result.
     *
     * Unfortunately, on PHP 5.6, it seems the PostgreSQL client library is pretty limited:
     * - there is no other way of getting notices of successful statements than using pg_last_notice() (except
     *   pg_trace(), which would write the frontend/backend communication to a file - then, the file would have to be
     *   read and truncated, which would result in quite some overhead);
     * - yet, it only reports the last notice - thus, none but the last notice of a single successful statement can be
     *   caught by any means;
     * - there is no clearing mechanism, thus, successful statement emitting the same notice as the previous statement
     *   is indistinguishable from a statement emitting nothing; moreover, statements with no notice do not clear the
     *   notice returned by pg_last_notice(); last but not least, it is connection-wide, thus, notion of last received
     *   notice must be kept on the whole connection, and a notice found out by get_last_notice() should only be
     *   reported if different from the last one.
     *
     * @todo update the docs for PHP 7.1, which is currently a requirement for Ivory
     *
     * @return string|null notice emitted for this result, or <tt>null</tt> if no notice was emitted or it was
     *                       indistinguishable from previous notices
     */
    function getLastNotice(): ?string;

    /**
     * @return string the command tag, e.g., <tt>SELECT 2</tt> or <tt>CREATE FUNCTION</tt>
     * @see http://www.postgresql.org/docs/9.4/static/protocol-message-formats.html
     */
    function getCommandTag(): string;
}
