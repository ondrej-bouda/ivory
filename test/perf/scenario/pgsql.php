<?php
declare(strict_types=1);

class PgSQLPerformanceTest implements IPerformanceTest
{
    private $conn;

    public function connect(string $connString, string $searchPathSchema)
    {
        $this->conn = pg_connect($connString, PGSQL_CONNECT_FORCE_NEW);
        $res = pg_query($this->conn, "SET search_path = $searchPathSchema");
        pg_free_result($res);
    }

    public function trivialQuery()
    {
        $res = pg_query($this->conn, 'SELECT 1');
        pg_free_result($res);
    }

    public function userAuthentication(string $email, string $password): int
    {
        $res = pg_query_params($this->conn, 'SELECT * FROM usr WHERE lower(email) = lower($1)', [$email]);
        if (!$res) {
            exit('Error authenticating the user');
        }
        $user = pg_fetch_assoc($res);
        pg_free_result($res);

        if ($user['pwd_hash'] !== md5($password)) {
            exit('Invalid password');
        }
        if ($user['is_active'] == 'f') {
            exit('User inactive');
        }

        $res = pg_query_params(
            $this->conn,
            'UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = $1',
            [$user['id']]
        );
        pg_free_result($res);
        if ($user['last_login']) {
            echo 'Welcome back since ' . date('n/j/Y H:i:s', strtotime($user['last_login'])) . "\n";
        }
        else {
            echo "Welcome!\n";
        }

        return $user['id'];
    }

    public function starredItems(int $userId)
    {
        $res = pg_query_params($this->conn,
            'SELECT item.id, item.name, item.description
             FROM usr_starred_item
                  JOIN item ON item.id = usr_starred_item.item_id
             WHERE usr_starred_item.usr_id = $1
             ORDER BY usr_starred_item.star_time DESC, item.name',
            [$userId]
        );
        $items = [];
        while (($row = pg_fetch_assoc($res))) {
            $items[$row['id']] = $row;
            $items[$row['id']]['categories'] = [];
        }
        pg_free_result($res);
        if ($items) {
            $itemIdList = implode(',', array_keys($items));
            $res = pg_query($this->conn,
                "SELECT item_id, category_id, name AS category_name
                 FROM item_category
                      JOIN category ON category.id = category_id
                 WHERE item_id IN ($itemIdList)
                 ORDER BY category_name, category_id"
            );
            while (($row = pg_fetch_assoc($res))) {
                $items[$row['item_id']]['categories'][$row['category_id']] = $row['category_name'];
            }
            pg_free_result($res);
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
        $res = pg_query_params($this->conn,
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
             WHERE category_id = $1
             GROUP BY item.id
             ORDER BY item.introduction_date DESC, item.name, item.id',
            [$categoryId]
        );
        echo "Category $categoryId:\n";
        while (($row = pg_fetch_assoc($res))) {
            printf('#%d %s, introduced %s: %s',
                $row['id'], $row['name'],
                date('n/j/Y', strtotime($row['introduction_date'])),
                $row['description']
            );
            foreach (json_decode($row['params']) as $parName => $parValue) {
                echo "; $parName: " . strtr(var_export($parValue, true), "\n", ' ');
            }
            echo "\n";
        }
        pg_free_result($res);
    }

    public function disconnect()
    {
        pg_close($this->conn);
        $this->conn = null;
    }
}
