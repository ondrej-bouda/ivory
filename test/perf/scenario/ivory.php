<?php
declare(strict_types=1);

use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Ivory\Connection\IConnection;
use Ivory\Ivory;
use Ivory\Query\SqlRelationDefinition;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;

class IvoryPerformanceTest implements IPerformanceTest
{
    /** Flag to use synchronous connection (instead of the asynchronous default). */
    const SYNCHRONOUS = 1;
    /** Flag to use no cache. If neither this nor `FILE_CACHE` is used, a memory cache is used. */
    const NO_CACHE = 2;
    /** Flag to use a file-based cache. If neither this nor `NO_CACHE` is used, a memory cache is used. */
    const FILE_CACHE = 4;
    /** Flag to use a cursor instead of fetching the whole result set at once. */
    const CURSOR = 8;

    const FILE_CACHE_DIR = __DIR__ . '/out';

    private $async;
    private $useCursor;
    private $bufferSize;
    /** @var IConnection */
    private $conn;

    public function __construct(int $options = 0, int $bufferSize = null)
    {
        $this->async = (($options & self::SYNCHRONOUS) == 0);
        $this->useCursor = (($options & self::CURSOR) != 0);
        $this->bufferSize = $bufferSize;

        if ($options & self::FILE_CACHE) {
            $fsAdapter = new Local(self::FILE_CACHE_DIR);
            $fs = new Filesystem($fsAdapter);
            $cachePool = new FilesystemCachePool($fs);
            $cachePool->clear();
            Ivory::setDefaultCacheImpl($cachePool);
        } elseif (!($options & self::NO_CACHE)) {
            Ivory::setDefaultCacheImpl(new ArrayCachePool());
        }
    }

    public function connect(string $connString, string $searchPathSchema)
    {
        $this->conn = Ivory::setupNewConnection($connString);

        if ($this->async) {
            $this->conn->connect(function (IConnection $conn) use ($searchPathSchema) {
                $conn->getConfig()->setForSession('search_path', $searchPathSchema);
            });
        } else {
            $this->conn->connectWait();
            $this->conn->getConfig()->setForSession('search_path', $searchPathSchema);
        }
    }

    public function trivialQuery()
    {
        $this->conn->querySingleValue('SELECT 1');
    }

    public function userAuthentication(string $email, string $password): int
    {
        try {
            $user = $this->conn->querySingleTuple(
                'SELECT * FROM usr WHERE lower(email) = lower(%s)',
                $email
            );
            assert($user !== null);
        } catch (Exception $e) {
            exit('Error authenticating the user');
        }

        if ($user->pwd_hash !== md5($password)) {
            exit('Invalid password');
        }
        if (!$user->is_active) {
            exit('User inactive');
        }

        $this->conn->command('UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = %int', $user->id);
        if ($user->last_login) {
            echo 'Welcome back since ' . $user->last_login->format('n/j/Y H:i:s') . "\n";
        } else {
            echo "Welcome!\n";
        }

        return $user->id;
    }

    public function starredItems(int $userId)
    {
        $relDef = SqlRelationDefinition::fromPattern(
            'SELECT item.id, item.name, item.description, item.introduction_date,
                    array_agg((category.id, category.name) ORDER BY category.name, category.id)
                      FILTER (WHERE category.id IS NOT NULL)
                      AS categories
             FROM usr_starred_item
                  JOIN item ON item.id = usr_starred_item.item_id
                  LEFT JOIN (item_category
                             JOIN category ON category.id = item_category.category_id
                            ) ON item_category.item_id = item.id
             WHERE usr_starred_item.usr_id = %int
             GROUP BY item.id, usr_starred_item.star_time
             ORDER BY usr_starred_item.star_time DESC, item.name',
            $userId
        );
        if ($this->useCursor) {
            $tx = $this->conn->startTransaction();
            $traversable = $this->conn->declareCursor('starred', $relDef);
            if ($this->bufferSize !== null) {
                $traversable = $traversable->getIterator($this->bufferSize);
            }
        } else {
            $tx = null;
            $traversable = $this->conn->query($relDef);
        }
        echo "Your starred items:\n";
        foreach ($traversable as $item) {
            printf('#%d %s', $item->id, $item->name);
            if ($item->categories) {
                $catStrings = [];
                foreach ($item->categories as $cat) {
                    $catStrings[] = sprintf('#%d %s', $cat[0], $cat[1]);
                }
                echo ' (' . implode(', ', $catStrings) . ')';
            }
            echo ': ';
            echo $item->description;
            echo "\n";
        }
        if ($tx !== null) {
            $tx->rollback();
        }
    }

    public function categoryItems(int $categoryId)
    {
        $relDef = SqlRelationDefinition::fromPattern(
            'SELECT item.id, item.name, item.description, item.introduction_date,
                    COALESCE(
                      json_object_agg(param.name, item_param_value.value ORDER BY param.priority DESC, param.name)
                        FILTER (WHERE param.id IS NOT NULL),
                      json_build_object()
                    ) AS params
             FROM item_category
                  JOIN item ON item.id = item_category.item_id
                  LEFT JOIN (item_param_value
                             JOIN param ON param.id = item_param_value.param_id
                            ) ON item_param_value.item_id = item.id
             WHERE category_id = %int
             GROUP BY item.id
             ORDER BY item.introduction_date DESC, item.name, item.id',
            $categoryId
        );
        if ($this->useCursor) {
            $tx = $this->conn->startTransaction();
            $traversable = $this->conn->declareCursor('items', $relDef);
            if ($this->bufferSize !== null) {
                $traversable = $traversable->getIterator($this->bufferSize);
            }
        } else {
            $tx = null;
            $traversable = $this->conn->query($relDef);
        }
        echo "Category $categoryId:\n";
        foreach ($traversable as $row) {
            printf('#%d %s, introduced %s: %s',
                $row->id, $row->name,
                $row->introduction_date->format('n/j/Y'),
                $row->description
            );
            foreach ($row->params->getValue() as $parName => $parValue) {
                echo "; $parName: " . strtr(var_export($parValue, true), "\n", ' ');
            }
            echo "\n";
        }
        if ($tx !== null) {
            $tx->rollback();
        }
    }

    public function disconnect()
    {
        Ivory::dropConnection($this->conn);
        $this->conn = null;
    }
}
