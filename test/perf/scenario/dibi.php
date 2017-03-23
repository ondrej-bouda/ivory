<?php


class DibiPerformanceTest implements IPerformanceTest
{
    const LAZY = 1;

    private $lazy = false;
    /** @var Dibi\Connection */
    private $conn;

    public function __construct(int $options = 0)
    {
        $this->lazy = (bool)($options & self::LAZY);
    }

    public function connect(string $connString, string $searchPathSchema)
    {
        $this->conn = new Dibi\Connection([
            'driver' => 'postgre',
            'string' => $connString,
            'schema' => $searchPathSchema,
            'lazy' => $this->lazy,
        ]);
    }

    public function trivialQuery()
    {
        $this->conn->select('1')->fetchSingle();
    }

    public function userAuthentication(string $email, string $password): int
    {
        $user = $this->conn->fetch('SELECT * FROM usr WHERE lower(email) = lower(%s)', $email);
        if (!$user) {
            exit('Error authenticating the user');
        }
        if ($user['pwd_hash'] !== md5($password)) {
            exit('Invalid password');
        }
        if (!$user['is_active']) {
            exit('User inactive');
        }

        $this->conn->query('UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = %i', $user['id']);
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
        $res = $this->conn->query(
            'SELECT item.id, item.name, item.description
             FROM usr_starred_item
                  JOIN item ON item.id = usr_starred_item.item_id
             WHERE usr_starred_item.usr_id = %i
             ORDER BY usr_starred_item.star_time DESC, item.name',
            $userId
        );
        $items = [];
        foreach ($res as $row) {
            $arr = $row->toArray();
            $arr['categories'] = [];
            $items[$arr['id']] = $arr;
        }
        unset($res);
        if ($items) {
            $res = $this->conn->query(
                "SELECT item_id, category_id, name AS category_name
                 FROM item_category
                      JOIN category ON category.id = category_id
                 WHERE item_id IN %in
                 ORDER BY category_name, category_id",
                array_keys($items)
            );
            foreach ($res as $row) {
                $items[$row['item_id']]['categories'][$row['category_id']] = $row['category_name'];
            }
        }

        echo "Your starred items:\n";
        foreach ($items as $item) {
            printf('#%d %s', $item['id'], $item['name']);
            if ($item['categories']) {
                $catStrings = [];
                foreach ($item['categories'] as $catId => $catName) {
                    $catStrings[] = sprintf('#%d %s', $catId, $catName);
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
        $res = $this->conn->query(
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
             WHERE category_id = %i
             GROUP BY item.id
             ORDER BY item.introduction_date DESC, item.name, item.id',
            $categoryId
        );
        echo "Category $categoryId:\n";
        foreach ($res as $row) {
            printf('#%d %s, introduced %s: %s',
                $row['id'], $row['name'],
                $row['introduction_date']->format('n/j/Y'),
                $row['description']
            );
            foreach (json_decode($row['params']) as $parName => $parValue) {
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
