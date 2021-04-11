<?php
declare(strict_types=1);
namespace Ivory;

use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Relation\ITuple;
use PHPUnit\Framework\Constraint;
use PHPUnit\Framework\TestCase;

abstract class IvoryTestCase extends TestCase
{
    /** @var resource|null connection for setting up and cleaning up fixture */
    private $pgConn = null;
    /** @var IConnection|null Ivory connection to provide to the tests */
    private $ivoryConn = null;

    /** @var bool whether in the error-interrupt mode */
    private $errorInterrupt = true;
    /** @var callable|null */
    private $origErrHandler = null;
    /** @var array error exceptions triggered in the non-interrupt mode and not yet asserted */
    private $triggeredErrors = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->pgConn = $this->connectTestDatabase();
        $this->initTestDatabase();

        $this->triggeredErrors = [];
    }

    protected function tearDown(): void
    {
        if (!$this->errorInterrupt) {
            $this->errorInterruptMode();
        }
        if ($this->ivoryConn !== null) {
            $this->ivoryConn->disconnect();
        }

        if ($this->pgConn !== null) {
            $this->cleanUpTestDatabase();
            $this->disconnectTestDatabase();
        }

        parent::tearDown();
    }

    /**
     * @param int $errorTypes bitwise combination of PHP error-level constants <tt>E_*</tt>;
     *                        only those errors will be caught and preserved, other errors will still be handled by the
     *                          original error handler
     */
    protected function errorNonInterruptMode(int $errorTypes = E_ALL): void
    {
        $origHandler = set_error_handler(
            function ($errNo, $errStr, $errFile, $errLine) use ($errorTypes) {
                if ($errNo & $errorTypes) {
                    $this->triggeredErrors[] = [
                        'type' => $errNo,
                        'msg' => $errStr,
                        'file' => $errFile,
                        'line' => $errLine,
                    ];
                } else {
                    call_user_func($this->origErrHandler, $errNo, $errStr, $errFile, $errLine);
                }
            },
            E_ALL
        );

        if ($this->errorInterrupt) {
            $this->origErrHandler = $origHandler;
            $this->errorInterrupt = false;
        }
    }

    protected function errorInterruptMode(): void
    {
        if (!$this->errorInterrupt) {
            restore_error_handler();
            $this->origErrHandler = null;
            $this->errorInterrupt = true;
        }
    }

    /**
     * Asserts that the given piece of code throws an exception of the given type and exception message.
     *
     * Adapted from https://github.com/sebastianbergmann/phpunit/issues/1798#issuecomment-134219493
     *
     * @param string|string[] $expectedTypeOrTypeMessagePair either the expected type, or pair (expected type, message)
     * @param \Closure $function piece of code within which an exception is expected to be thrown
     * @param string $message message to show upon failure
     */
    protected static function assertException($expectedTypeOrTypeMessagePair, \Closure $function, $message = '')
    {
        if (is_array($expectedTypeOrTypeMessagePair)) {
            [$expectedType, $expectedMessage] = $expectedTypeOrTypeMessagePair;
        } else {
            $expectedType = $expectedTypeOrTypeMessagePair;
            $expectedMessage = null;
        }

        $exception = null;

        try {
            call_user_func($function);
        } catch (\Exception $e) {
            $exception = $e;
        }

        self::assertThat($exception, new Constraint\Exception($expectedType), $message);

        if ($expectedMessage !== null) {
            self::assertThat($exception, new Constraint\ExceptionMessage($expectedMessage), $message);
        }
    }

    /**
     * @param array $expectedValues
     * @param iterable $actualTuples
     * @param string|int|\Closure $attribute what to take from each tuple; like for {@link ITuple::value()}
     * @param string $message
     */
    protected static function assertTupleVals(array $expectedValues, iterable $actualTuples, $attribute, $message = '')
    {
        $actualValues = [];
        foreach ($actualTuples as $tuple) {
            assert($tuple instanceof ITuple);
            $actualValues[] = $tuple->value($attribute);
        }

        self::assertSame($expectedValues, $actualValues, $message);
    }

    /**
     * @param string $expectedErrMsgRegex regular expression the error message is expected to match against
     * @param int $expectedErrType the expected error level;
     *                             one or more PHP error-level constants <tt>E_*</tt> may be given using bitwise OR,
     *                               the error satisfying such specification if it matches any of the contained levels;
     *                             skip with <tt>E_ALL</tt> if the error type does not matter
     * @param string $message
     */
    protected function assertErrorTriggered(
        string $expectedErrMsgRegex,
        int $expectedErrType = E_ALL,
        string $message = ''
    ): void {
        if (!$this->triggeredErrors) {
            $failMsg = 'There were no (more) errors triggered';
            if (strlen($message) > 0) {
                $failMsg = "$message ($failMsg)";
            }
            self::fail($failMsg);
        }

        $err = array_shift($this->triggeredErrors);

        if (!($expectedErrType & $err['type'])) {
            $failMsg = sprintf(
                'Wrong error type: expected %s, actual %s',
                self::errorTypeBitmaskToString($expectedErrType),
                self::errorTypeToString($err['type'])
            );
            if (strlen($message) > 0) {
                $failMsg = "$message ($failMsg)";
            }
            self::fail($failMsg);
        }

        self::assertRegExp($expectedErrMsgRegex, $err['msg'], $message);
    }

    protected function assertErrorsTriggered(
        int $count,
        string $expectedErrMsgRegex,
        int $expectedErrType = E_ALL,
        string $message = ''
    ): void {
        for ($i = 1; $i <= $count; $i++) {
            $this->assertErrorTriggered(
                $expectedErrMsgRegex, $expectedErrType,
                "$message (error $i of $count expected)"
            );
        }
    }

    private const ERROR_TYPES = [
        E_ERROR => 'E_ERROR',
        E_WARNING => 'E_WARNING',
        E_PARSE => 'E_PARSE',
        E_NOTICE => 'E_NOTICE',
        E_CORE_ERROR => 'E_CORE_ERROR',
        E_CORE_WARNING => 'E_CORE_WARNING',
        E_COMPILE_ERROR => 'E_COMPILE_ERROR',
        E_COMPILE_WARNING => 'E_COMPILE_WARNING',
        E_USER_ERROR => 'E_USER_ERROR',
        E_USER_WARNING => 'E_USER_WARNING',
        E_USER_NOTICE => 'E_USER_NOTICE',
        E_STRICT => 'E_STRICT',
        E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
        E_DEPRECATED => 'E_DEPRECATED',
        E_USER_DEPRECATED => 'E_USER_DEPRECATED',
    ];

    private static function errorTypeBitmaskToString(int $errorTypeBitmask): string
    {
        $contained = [];
        $absent = [];

        $mx = max(array_keys(self::ERROR_TYPES));
        for ($i = 1; $i <= $mx; $i <<= 1) {
            if ($errorTypeBitmask & $i) {
                $contained[] = $i;
            } else {
                $absent[] = $i;
            }
        }

        if (count($absent) <= 3) {
            $absStrs = ['E_ALL'];
            foreach ($absent as $t) {
                $absStrs[] = '~' . self::errorTypeToString($t);
            }
            return implode(' & ', $absStrs);
        } else {
            $contStrs = [];
            foreach ($contained as $t) {
                $contStrs[] = self::errorTypeToString($t);
            }
            return implode(' | ', $contStrs);
        }
    }

    private static function errorTypeToString(int $errorType): string
    {
        return (self::ERROR_TYPES[$errorType] ?? (string)$errorType);
    }

    protected function assertNoMoreErrors(string $message = ''): void
    {
        if ($this->triggeredErrors) {
            $failMsg = "There is error '{$this->triggeredErrors[0]['msg']}'";
            if (count($this->triggeredErrors) > 1) {
                $failMsg .= sprintf(
                    ' and %d %s',
                    count($this->triggeredErrors) - 1,
                    (count($this->triggeredErrors) > 2 ? 'others' : 'other')
                );
            }
            if (strlen($message) > 0) {
                $failMsg = "$message ($failMsg)";
            }
            self::fail($failMsg);
        }

        self::assertEmpty($this->triggeredErrors, $message);
    }

    private function connectTestDatabase()
    {
        $params = [
            'host' => $GLOBALS['DB_HOST'],
            'port' => $GLOBALS['DB_PORT'],
            'dbname' => $GLOBALS['DB_DBNAME'],
            'user' => $GLOBALS['DB_USER'],
            'password' => $GLOBALS['DB_PASSWD'],
        ];
        $pieces = [];
        foreach ($params as $key => $value) {
            if (strlen($value) > 0) {
                $pieces[] = "$key='" . strtr($value, ["'" => "\\'", '\\' => '\\\\']) . "'";
            }
        }
        $connStr = implode(' ', $pieces);
        $conn = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW);
        if (!$conn) {
            throw new \RuntimeException('Cannot connect to the test database. Please, check test/phpunit.xml');
        }
        return $conn;
    }

    private function disconnectTestDatabase(): void
    {
        assert($this->pgConn !== null);
        pg_close($this->pgConn);
    }

    private function pgQuery(string $query): void
    {
        assert($this->pgConn !== null);
        $res = pg_query($this->pgConn, $query);
        if (!$res) {
            throw new \RuntimeException('Error executing query: ' . pg_last_error($this->pgConn));
        }
    }

    final protected function getIvoryConnection(): IConnection
    {
        if ($this->ivoryConn === null) {
            $this->ivoryConn = $this->createNewIvoryConnection();
        }

        return $this->ivoryConn;
    }

    final protected function createNewIvoryConnection(string $name = 'default'): IConnection
    {
        $params = ConnectionParameters::fromArray([
            ConnectionParameters::HOST => ($GLOBALS['DB_HOST'] ? : null),
            ConnectionParameters::PORT => ($GLOBALS['DB_PORT'] ? : null),
            ConnectionParameters::USER => $GLOBALS['DB_USER'],
            ConnectionParameters::PASSWORD => $GLOBALS['DB_PASSWD'],
            ConnectionParameters::DBNAME => $GLOBALS['DB_DBNAME'],
        ]);
        $coreFactory = Ivory::getCoreFactory();
        return $coreFactory->createConnection($name, $params);
    }

    private function cleanUpTestDatabase(): void
    {
        $this->pgQuery(
            'DROP TABLE IF EXISTS artist, album, album_artist, album_track'
        );
    }

    private function initTestDatabase(): void
    {
        $this->pgQuery(<<<'SQL'
DROP TABLE IF EXISTS artist, album, album_artist, album_track;

CREATE TABLE artist (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    is_active BOOL
);
CREATE TABLE album (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    year SMALLINT,
    released DATE
);
CREATE TABLE album_artist (
    album_id BIGINT NOT NULL REFERENCES album,
    artist_id BIGINT NOT NULL REFERENCES artist,
    PRIMARY KEY (album_id, artist_id)
);
CREATE TABLE album_track (
    album_id BIGINT NOT NULL,
    disc_no SMALLINT NOT NULL DEFAULT 1,
    track_no SMALLINT NOT NULL,
    name VARCHAR(100) NOT NULL,
    PRIMARY KEY (album_id, disc_no, track_no)
);

CREATE EXTENSION IF NOT EXISTS hstore;


INSERT INTO artist (id, name, is_active) VALUES
    (1, 'The Piano Guys',  TRUE),
    (2, 'Metallica',       FALSE),
    (3, 'Tommy Emmanuel',  NULL),
    (4, 'Robbie Williams', NULL),
    (5, 'B-Side Band',     TRUE);
SELECT setval('artist_id_seq'::REGCLASS, (SELECT MAX(id) FROM artist));

INSERT INTO album (id, name, year, released) VALUES
    (1, 'The Piano Guys', 2012, '2012-10-02'),
    (2, 'Black Album',    1991, '1991-08-12'),
    (3, 'S & M',          1999, '1999-11-23'),
    (4, 'Live One',       2005, '2005-01-01'),
    (5, 'Meeting Point',  2014, '2014-10-27'),
    (6, 'Deska',          2014, '2014-12-10');
SELECT setval('album_id_seq'::REGCLASS, (SELECT MAX(id) FROM album));

INSERT INTO album_artist (album_id, artist_id) VALUES
    (1, 1),
    (2, 2),
    (3, 2),
    (4, 3),
    (5, 5),
    (6, 5);

INSERT INTO album_track (album_id, disc_no, track_no, name) VALUES
    (1, 1, 1, 'Pavane // Titanium'),
    (1, 1, 2, 'Peponi (Paradise)'),
    (1, 1, 3, 'Code Name Vivaldi'),
    (1, 1, 4, 'Beethoven´s 5 Secrets'),
    (1, 1, 5, 'Simple Gifts Over The Rainbow'),
    (1, 1, 6, 'Cello Wars'),
    (1, 1, 7, 'Arwen´s Vigil'),
    (1, 1, 8, 'Moonlight'),
    (1, 1, 9, 'A Thousand Years'),
    (1, 1, 10, 'Michael Meets Mozart'),
    (1, 1, 11, 'The Cello Song'),
    (1, 1, 12, 'Rolling in the Deep'),
    (1, 1, 13, 'Whats Makes you Beautiful'),
    (1, 1, 14, 'Bring him home'),
    (1, 1, 15, 'Without You'),
    (1, 1, 16, 'Nearer my God to thee'),
    (2, 1, 1, 'Enter Sandman'),
    (2, 1, 2, 'Sad But True'),
    (2, 1, 3, 'Holier Than Thou'),
    (2, 1, 4, 'The Unforgiven'),
    (2, 1, 5, 'Wherever I May Roam'),
    (2, 1, 6, 'Don''t Tread on Me'),
    (2, 1, 7, 'Through the Never'),
    (2, 1, 8, 'Nothing Else Matters'),
    (2, 1, 9, 'Of Wolf And Man'),
    (2, 1, 10, 'The God That Failed'),
    (2, 1, 11, 'My Friend of Misery'),
    (3, 1, 1, 'The Struggle Within'),
    (3, 1, 2, 'The Ecstasy Of Gold'),
    (3, 1, 3, 'The Call Of The Ktulu'),
    (3, 1, 4, 'Master Of Puppets'),
    (3, 1, 5, 'Of Wolf And Man'),
    (3, 1, 6, 'The Thing That Should Not Be'),
    (3, 1, 7, 'Fuel'),
    (3, 1, 8, 'The Memory Remains'),
    (3, 1, 9, 'No Leave Clover'),
    (3, 1, 10, 'Hero Of The Day'),
    (3, 1, 11, 'Devil''s Dance'),
    (3, 2, 1, 'Nothing Else Matters'),
    (3, 2, 2, 'Until It Sleeps'),
    (3, 2, 3, 'For Whom The Bell Tolls'),
    (3, 2, 4, '- Human'),
    (3, 2, 5, 'Wherever I May Roam'),
    (3, 2, 6, 'Outlaw Torn'),
    (3, 2, 7, 'Sad But True'),
    (3, 2, 8, 'One'),
    (3, 2, 9, 'Enter Sandman'),
    (3, 2, 10, 'Battery'),
    (4, 1, 1, 'Beatles Medley'),
    (4, 1, 2, 'Peter Allen Medley/Waltzing Mathilda'),
    (4, 1, 3, 'Classical Gas'),
    (4, 1, 4, 'Old Fashioned Love Song'),
    (4, 1, 5, 'Son Of A Gun'),
    (4, 1, 6, 'Dixie McGuire'),
    (4, 1, 7, 'Country Wide'),
    (4, 1, 8, 'Saltwater'),
    (4, 1, 9, 'Borsalino'),
    (4, 1, 10, 'Up From Down Under'),
    (4, 1, 11, 'Morning Aire'),
    (4, 1, 12, 'Those Who Wait'),
    (4, 1, 13, 'Michelle'),
    (4, 1, 14, 'Questions'),
    (4, 1, 15, 'Angelina'),
    (4, 1, 16, 'Precious Time/That''s The Spirit'),
    (4, 1, 17, 'Mona Lisa'),
    (4, 1, 18, 'Mombasa'),
    (4, 2, 1, 'Amazing Grace'),
    (4, 2, 2, 'House Of The Rising Sun'),
    (4, 2, 3, 'Guitar Rag'),
    (4, 2, 4, 'Blue Moon'),
    (4, 2, 5, 'Mozzarella Tarantella'),
    (4, 2, 6, 'Guitar Boogie'),
    (4, 2, 7, 'Train To Dusseldorf'),
    (4, 2, 8, 'One Mint Julep'),
    (4, 2, 9, 'The Hunt'),
    (4, 2, 10, 'Initiation'),
    (5, 1, 1, 'Nebe je jasné'),
    (5, 1, 2, 'Birdland'),
    (5, 1, 3, 'Tajné místo'),
    (5, 1, 4, 'What''s This'),
    (5, 1, 5, 'Tržnice světa'),
    (5, 1, 6, 'Radio Time'),
    (5, 1, 7, 'Orient Express'),
    (5, 1, 8, 'My French Honey'),
    (5, 1, 9, 'Města nad řekou'),
    (5, 1, 10, 'One Last Call'),
    (5, 1, 11, 'Largo Live'),
    (6, 1, 1, 'Meeting Point'),
    (6, 1, 2, 'Podivnost'),
    (6, 1, 3, 'Starý Landštejn'),
    (6, 1, 4, 'Piece of Cake'),
    (6, 1, 5, 'Place du Grand Sablon'),
    (6, 1, 6, 'Přístaviště'),
    (6, 1, 7, 'Chaazi'),
    (6, 1, 8, 'The Shadow of Vulcan'),
    (6, 1, 9, 'Ornis');
SQL
        );
    }
}
