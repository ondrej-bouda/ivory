<?php
namespace Ivory;

use Ivory\Connection\Connection;
use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;

abstract class IvoryTestCase extends \PHPUnit_Extensions_Database_TestCase
{
    /** @var \PDO only instantiated once for test clean-up/fixture load */
    private static $pdo = null;

    /** @var \PHPUnit_Extensions_Database_DB_IDatabaseConnection instantiated once per test */
    private $phpUnitConn = null;

    /** @var IConnection */
    private $ivoryConn = null;


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

    protected function getIvoryConnection()
    {
        if ($this->ivoryConn === null) {
            $this->ivoryConn = new Connection('default', new ConnectionParameters([
                'host' => ($GLOBALS['DB_HOST'] ? : null),
                'port' => ($GLOBALS['DB_PORT'] ? : null),
                'user' => $GLOBALS['DB_USER'],
                'password' => $GLOBALS['DB_PASSWD'],
                'dbname' => $GLOBALS['DB_DBNAME'],
            ]));
        }

        return $this->ivoryConn;
    }

    protected function newDatabaseTester()
    {
        return new IvoryTester($this->getConnection());
    }

    private function initDbSchema()
    {
        $this->phpUnitConn->getConnection()->exec(<<<SQL
DROP TABLE IF EXISTS artist, album, album_artist, album_track;

CREATE TABLE artist (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  is_active BOOL
);
CREATE TABLE album (
  id BIGSERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  year SMALLINT
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
            ],
            'album' => [
                ['id' => 1, 'name' => 'The Piano Guys', 'year' => 2012],
                ['id' => 2, 'name' => 'Black Album', 'year' => 1991],
                ['id' => 3, 'name' => 'S & M', 'year' => 1999],
                ['id' => 4, 'name' => 'Live One', 'year' => 2005],
            ],
            'album_artist' => [
                ['album_id' => 1, 'artist_id' => 1],
                ['album_id' => 2, 'artist_id' => 2],
                ['album_id' => 3, 'artist_id' => 2],
                ['album_id' => 4, 'artist_id' => 3],
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
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 1,  'name' => 'The Struggle Within'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 2,  'name' => 'The Ecstasy Of Gold'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 3,  'name' => 'The Call Of The Ktulu'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 4,  'name' => 'Master Of Puppets'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 5,  'name' => 'Of Wolf And Man'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 6,  'name' => 'The Thing That Should Not Be'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 7,  'name' => 'Fuel'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 8,  'name' => 'The Memory Remains'],
                ['album_id' => 3, 'disc_no' => 1, 'track_no' => 9,  'name' => 'No Leave Clover'],
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
            ],
        ]);
    }
}
