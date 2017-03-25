<?php

class LaravelPerformanceTest implements IPerformanceTest
{
    /** @var \Illuminate\Database\ConnectionInterface */
    private $conn;

    public function connect(string $connString, string $searchPathSchema)
    {
        $config = ['schema' => $searchPathSchema, 'charset' => 'UTF-8'];
        $trans = [
            'host' => 'host',
            'port' => 'port',
            'user' => 'username',
            'password' => 'password',
            'dbname' => 'database',
        ];
        foreach (explode(' ', $connString) as $pair) {
            list($k, $v) = explode('=', $pair);
            $config[$trans[$k]] = $v;
        }

        $connector = new \Illuminate\Database\Connectors\PostgresConnector();
        $pdo = $connector->connect($config);

        $this->conn = new \Illuminate\Database\PostgresConnection($pdo);
    }

    public function trivialQuery()
    {
        $this->conn->selectOne('SELECT 1');
    }

    public function userAuthentication(string $email, string $password): int
    {
        $user = $this->conn->selectOne('SELECT * FROM usr WHERE lower(email) = lower(?)', [$email]);
        if (!$user) {
            exit('Error authenticating the user');
        }
        if ($user->pwd_hash !== md5($password)) {
            exit('Invalid password');
        }
        if (!$user->is_active) {
            exit('User inactive');
        }

        $this->conn->statement('UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = ?', [$user->id]);
        if ($user->last_login) {
            echo 'Welcome back since ' . date('n/j/Y H:i:s', strtotime($user->last_login)) . "\n";
        }
        else {
            echo "Welcome!\n";
        }

        return $user->id;
    }

    public function starredItems(int $userId)
    {
        $res = $this->conn->select(
            'SELECT item.id, item.name, item.description
             FROM usr_starred_item
                  JOIN item ON item.id = usr_starred_item.item_id
             WHERE usr_starred_item.usr_id = ?
             ORDER BY usr_starred_item.star_time DESC, item.name',
            [$userId]
        );
        $items = [];
        foreach ($res as $row) {
            $items[$row->id] = $row;
            $items[$row->id]->categories = [];
        }
        if ($items) {
            $itemIdList = implode(',', array_keys($items));
            $res = $this->conn->select(
                "SELECT item_id, category_id, name AS category_name
                 FROM item_category
                      JOIN category ON category.id = category_id
                 WHERE item_id IN ($itemIdList)
                 ORDER BY category_name, category_id"
            );
            foreach ($res as $row) {
                $items[$row->item_id]->categories[$row->category_id] = $row->category_name;
            }
        }
        echo "Your starred items:\n";
        foreach ($items as $item) {
            printf('#%d %s', $item->id, $item->name);
            if ($item->categories) {
                $catStrings = [];
                foreach ($item->categories as $catId => $catName) {
                    $catStrings[] = sprintf('#%d %s', $catId, $catName);
                }
                echo ' (' . implode(', ', $catStrings) . ')';
            }
            echo ': ';
            echo $item->description;
            echo "\n";
        }
    }

    public function categoryItems(int $categoryId)
    {
        $res = $this->conn->select(
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
             WHERE category_id = ?
             GROUP BY item.id
             ORDER BY item.introduction_date DESC, item.name, item.id',
            [$categoryId]
        );
        echo "Category $categoryId:\n";
        foreach ($res as $row) {
            printf('#%d %s, introduced %s: %s',
                $row->id, $row->name,
                date('n/j/Y', strtotime($row->introduction_date)),
                $row->description
            );
            foreach (json_decode($row->params) as $parName => $parValue) {
                echo "; $parName: " . strtr(var_export($parValue, true), "\n", ' ');
            }
            echo "\n";
        }
    }

    public function disconnect()
    {
        $this->conn = null; // TODO: really no explicit method for disconnecting?
    }
}
