<?php
declare(strict_types=1);
namespace Ivory;

use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Relation\ITuple;
use PHPUnit\DbUnit\Database\Connection as DbUnitConnection;
use PHPUnit\DbUnit\TestCase;
use PHPUnit\Framework\Constraint;

abstract class IvoryTestCase extends TestCase
{
    /** @var \PDO only instantiated once for test clean-up/fixture load */
    private static $pdo = null;

    /** @var DbUnitConnection instantiated once per test */
    private $phpUnitConn = null;

    /** @var IConnection|null */
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
        $this->triggeredErrors = [];
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (!$this->errorInterrupt) {
            $this->errorInterruptMode();
        }
        if ($this->ivoryConn !== null) {
            $this->ivoryConn->disconnect();
        }
    }

    /**
     * @param int $errorTypes bitwise combination of PHP error-level constants <tt>E_*</tt>;
     *                        only those errors will be caught and preserved, other errors will still be handled by the
     *                          original error handler
     */
    protected function errorNonInterruptMode($errorTypes = E_ALL)
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

    protected function errorInterruptMode()
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
            list($expectedType, $expectedMessage) = $expectedTypeOrTypeMessagePair;
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
    protected function assertErrorTriggered($expectedErrMsgRegex, $expectedErrType = E_ALL, $message = '')
    {
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

    protected function assertErrorsTriggered($count, $expectedErrMsgRegex, $expectedErrType = E_ALL, $message = '')
    {
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

    private static function errorTypeBitmaskToString($errorTypeBitmask)
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

    private static function errorTypeToString($errorType)
    {
        return (self::ERROR_TYPES[$errorType] ?? $errorType);
    }

    /**
     * @param string $message
     */
    protected function assertNoMoreErrors($message = '')
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


    final protected function getConnection()
    {
        if ($this->phpUnitConn === null) {
            if (self::$pdo === null) {
                $dsnParts = [];
                if (!empty($GLOBALS['DB_HOST'])) {
                    $dsnParts[] = "host=$GLOBALS[DB_HOST]";
                }
                if (!empty($GLOBALS['DB_PORT'])) {
                    $dsnParts[] = "port=$GLOBALS[DB_PORT]";
                }
                $dsnParts[] = "dbname=$GLOBALS[DB_DBNAME]";

                $dsn = 'pgsql:' . implode(';', $dsnParts);
                self::$pdo = new \PDO($dsn, $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);
            }
            $this->phpUnitConn = $this->createDefaultDBConnection(self::$pdo, $GLOBALS['DB_DBNAME']);
            $this->initDbSchema();
        }

        return $this->phpUnitConn;
    }

    protected function getIvoryConnection(): IConnection
    {
        if ($this->ivoryConn === null) {
            $this->ivoryConn = $this->createNewIvoryConnection();
        }

        return $this->ivoryConn;
    }

    protected function createNewIvoryConnection(string $name = 'default'): IConnection
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

    /** @noinspection PhpMissingParentCallCommonInspection */
    protected function newDatabaseTester()
    {
        return new IvoryTester($this->getConnection());
    }

    private function initDbSchema()
    {
        $this->phpUnitConn->getConnection()->exec(<<<'SQL'
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
SQL
        );
    }

    protected function getDataSet()
    {
        return new ArrayDataSet([
            'artist' => [
                ['id' => 1, 'name' => 'The Piano Guys', 'is_active' => 't'],
                ['id' => 2, 'name' => 'Metallica', 'is_active' => 'f'],
                ['id' => 3, 'name' => 'Tommy Emmanuel', 'is_active' => null],
                ['id' => 4, 'name' => 'Robbie Williams', 'is_active' => null],
                ['id' => 5, 'name' => 'B-Side Band', 'is_active' => 't'],
            ],
            'album' => [
                ['id' => 1, 'name' => 'The Piano Guys', 'year' => 2012, 'released' => '2012-10-02'],
                ['id' => 2, 'name' => 'Black Album', 'year' => 1991, 'released' => '1991-08-12'],
                ['id' => 3, 'name' => 'S & M', 'year' => 1999, 'released' => '1999-11-23'],
                ['id' => 4, 'name' => 'Live One', 'year' => 2005, 'released' => '2005-01-01'],
                ['id' => 5, 'name' => 'Meeting Point', 'year' => 2014, 'released' => '2014-10-27'],
                ['id' => 6, 'name' => 'Deska', 'year' => 2014, 'released' => '2014-12-10'],
            ],
            'album_artist' => [
                ['album_id' => 1, 'artist_id' => 1],
                ['album_id' => 2, 'artist_id' => 2],
                ['album_id' => 3, 'artist_id' => 2],
                ['album_id' => 4, 'artist_id' => 3],
                ['album_id' => 5, 'artist_id' => 5],
                ['album_id' => 6, 'artist_id' => 5],
            ],
            'album_track' => [
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 1, 'name' => 'Pavane // Titanium'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 2, 'name' => 'Peponi (Paradise)'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 3, 'name' => 'Code Name Vivaldi'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 4, 'name' => 'Beethoven´s 5 Secrets'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 5, 'name' => 'Simple Gifts Over The Rainbow'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 6, 'name' => 'Cello Wars'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 7, 'name' => 'Arwen´s Vigil'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 8, 'name' => 'Moonlight'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 9, 'name' => 'A Thousand Years'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 10, 'name' => 'Michael Meets Mozart'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 11, 'name' => 'The Cello Song'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 12, 'name' => 'Rolling in the Deep'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 13, 'name' => 'Whats Makes you Beautiful'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 14, 'name' => 'Bring him home'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 15, 'name' => 'Without You'],
                ['album_id' => 1, 'disc_no' => 1, 'track_no' => 16, 'name' => 'Nearer my God to thee'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 1, 'name' => 'Enter Sandman'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 2, 'name' => 'Sad But True'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 3, 'name' => 'Holier Than Thou'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 4, 'name' => 'The Unforgiven'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 5, 'name' => 'Wherever I May Roam'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 6, 'name' => 'Don\'t Tread on Me'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 7, 'name' => 'Through the Never'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 8, 'name' => 'Nothing Else Matters'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 9, 'name' => 'Of Wolf And Man'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 10, 'name' => 'The God That Failed'],
                ['album_id' => 2, 'disc_no' => 1, 'track_no' => 11, 'name' => 'My Friend of Misery'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 1, 'name' => 'The Struggle Within'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 2, 'name' => 'The Ecstasy Of Gold'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 3, 'name' => 'The Call Of The Ktulu'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 4, 'name' => 'Master Of Puppets'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 5, 'name' => 'Of Wolf And Man'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 6, 'name' => 'The Thing That Should Not Be'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 7, 'name' => 'Fuel'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 8, 'name' => 'The Memory Remains'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 9, 'name' => 'No Leave Clover'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 10, 'name' => 'Hero Of The Day'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 11, 'name' => 'Devil\'s Dance'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 1, 'name' => 'Nothing Else Matters'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 2, 'name' => 'Until It Sleeps'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 3, 'name' => 'For Whom The Bell Tolls'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 4, 'name' => '- Human'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 5, 'name' => 'Wherever I May Roam'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 6, 'name' => 'Outlaw Torn'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 7, 'name' => 'Sad But True'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 8, 'name' => 'One'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 9, 'name' => 'Enter Sandman'],
                ['album_id' => 3, 'disc_no' => 2, 'track_no' => 10, 'name' => 'Battery'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 1, 'name' => 'Beatles Medley'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 2, 'name' => 'Peter Allen Medley/Waltzing Mathilda'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 3, 'name' => 'Classical Gas'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 4, 'name' => 'Old Fashioned Love Song'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 5, 'name' => 'Son Of A Gun'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 6, 'name' => 'Dixie McGuire'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 7, 'name' => 'Country Wide'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 8, 'name' => 'Saltwater'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 9, 'name' => 'Borsalino'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 10, 'name' => 'Up From Down Under'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 11, 'name' => 'Morning Aire'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 12, 'name' => 'Those Who Wait'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 13, 'name' => 'Michelle'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 14, 'name' => 'Questions'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 15, 'name' => 'Angelina'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 16, 'name' => 'Precious Time/That\'s The Spirit'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 17, 'name' => 'Mona Lisa'],
                ['album_id' => 4, 'disc_no' => 1, 'track_no' => 18, 'name' => 'Mombasa'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 1, 'name' => 'Amazing Grace'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 2, 'name' => 'House Of The Rising Sun'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 3, 'name' => 'Guitar Rag'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 4, 'name' => 'Blue Moon'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 5, 'name' => 'Mozzarella Tarantella'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 6, 'name' => 'Guitar Boogie'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 7, 'name' => 'Train To Dusseldorf'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 8, 'name' => 'One Mint Julep'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 9, 'name' => 'The Hunt'],
                ['album_id' => 4, 'disc_no' => 2, 'track_no' => 10, 'name' => 'Initiation'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 1, 'name' => 'Nebe je jasné'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 2, 'name' => 'Birdland'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 3, 'name' => 'Tajné místo'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 4, 'name' => "What's This"],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 5, 'name' => 'Tržnice světa'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 6, 'name' => 'Radio Time'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 7, 'name' => 'Orient Express'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 8, 'name' => 'My French Honey'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 9, 'name' => 'Města nad řekou'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 10, 'name' => 'One Last Call'],
                ['album_id' => 5, 'disc_no' => 1, 'track_no' => 11, 'name' => 'Largo Live'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 1, 'name' => 'Meeting Point'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 2, 'name' => 'Podivnost'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 3, 'name' => 'Starý Landštejn'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 4, 'name' => 'Piece of Cake'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 5, 'name' => 'Place du Grand Sablon'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 6, 'name' => 'Přístaviště'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 7, 'name' => 'Chaazi'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 8, 'name' => 'The Shadow of Vulcan'],
                ['album_id' => 6, 'disc_no' => 1, 'track_no' => 9, 'name' => 'Ornis'],
            ],
        ]);
    }
}
