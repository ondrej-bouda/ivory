<?php

class DoctrinePerformanceTest implements IPerformanceTest
{
    /** @var \Doctrine\DBAL\Connection */
    private $conn;

    public function connect(string $connString, string $searchPathSchema)
    {
        $config = new \Doctrine\DBAL\Configuration();
        $params = ['driver' => 'pdo_pgsql'];
        foreach (explode(' ', $connString) as $pair) {
            list($k, $v) = explode('=', $pair);
            $params[$k] = $v;
        }
        $this->conn = \Doctrine\DBAL\DriverManager::getConnection($params, $config);
        $this->conn->setFetchMode(\PDO::FETCH_ASSOC);
        $this->conn->exec("SET search_path = $searchPathSchema");
    }

    public function trivialQuery()
    {
        $this->conn->fetchColumn('SELECT 1');
    }

    public function userAuthentication(string $email, string $password): int
    {
        $user = $this->conn->fetchAssoc('SELECT * FROM usr WHERE lower(email) = lower(?)', [$email]);
        if (!$user) {
            exit('Error authenticating the user');
        }
        if ($user['pwd_hash'] !== md5($password)) {
            exit('Invalid password');
        }
        if (!$user['is_active']) {
            exit('User inactive');
        }

        $this->conn->executeQuery('UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = ?', [$user['id']]);
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
        $res = $this->conn->executeQuery(
            'SELECT item.id, item.name, item.description
             FROM usr_starred_item
                  JOIN item ON item.id = usr_starred_item.item_id
             WHERE usr_starred_item.usr_id = ?
             ORDER BY usr_starred_item.star_time DESC, item.name',
            [$userId]
        );
        $items = [];
        while (($row = $res->fetch())) {
            $items[$row['id']] = $row;
            $items[$row['id']]['categories'] = [];
        }
        if ($items) {
            $res = $this->conn->executeQuery(
                "SELECT item_id, category_id, name AS category_name
                 FROM item_category
                      JOIN category ON category.id = category_id
                 WHERE item_id IN (?)
                 ORDER BY category_name, category_id",
                [array_keys($items)],
                [\Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
            );
            while (($row = $res->fetch())) {
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
        $res = $this->conn->executeQuery(
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
        while (($row = $res->fetch())) {
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
    }

    public function disconnect()
    {
        $this->conn->close();
        $this->conn = null;
    }
}
