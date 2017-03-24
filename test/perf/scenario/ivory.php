<?php

use Ivory\Connection\IConnection;
use Ivory\Ivory;

class IvoryPerformanceTest implements IPerformanceTest
{
    const SYNCHRONOUS = 1;

    private $async;
    /** @var IConnection */
    private $conn;

    public function __construct(int $options = 0)
    {
        $this->async = !($options & self::SYNCHRONOUS);
    }

    public function connect(string $connString, string $searchPathSchema)
    {
        $this->conn = Ivory::setupConnection($connString);

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
        }
        catch (Exception $e) {
            exit('Error authenticating the user');
        }

        if ($user['pwd_hash'] !== md5($password)) {
            exit('Invalid password');
        }
        if (!$user['is_active']) {
            exit('User inactive');
        }

        $this->conn->command('UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = %int', $user['id']);
        if ($user['last_login']) {
            echo 'Welcome back since ' . $user['last_login']->format('n/j/Y H:i:s') . "\n";
        }
        else {
            echo "Welcome!\n";
        }

        return $user['id'];
    }

    public function starredItems(int $userId)
    {
        $rel = $this->conn->query(
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
        echo "Your starred items:\n";
        foreach ($rel as $item) {
            printf('#%d %s', $item['id'], $item['name']);
            if ($item['categories']) {
                $catStrings = [];
                foreach ($item['categories'] as $cat) {
                    $catStrings[] = sprintf('#%d %s', $cat[0], $cat[1]);
                }
                echo ' (' . implode(', ', $catStrings) . ')';
            }
            echo ': ';
            echo $item['description'];
            echo "\n";
        }
    }

    public function categoryItems(int $categoryId)
    {
        $rel = $this->conn->query(
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
        echo "Category $categoryId:\n";
        foreach ($rel as $row) {
            printf('#%d %s, introduced %s: %s',
                $row['id'], $row['name'],
                $row['introduction_date']->format('n/j/Y'),
                $row['description']
            );
            foreach ($row['params']->getValue() as $parName => $parValue) {
                echo "; $parName: " . strtr(var_export($parValue, true), "\n", ' ');
            }
            echo "\n";
        }
    }

    public function disconnect()
    {
        $this->conn->disconnect();
        $this->conn = null;
    }
}
