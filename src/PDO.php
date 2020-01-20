<?php

declare(strict_types=1);

/*
 * This file is part of the guanguans/soar-php.
 *
 * (c) 琯琯 <yzmguanguan@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

namespace Guanguans\SoarPHP;

use Guanguans\SoarPHP\Exceptions\InvalidArgumentException;
use PDO as BasePDO;
use PDOException;

class PDO extends BasePDO
{
    /**
     * @var \PDO
     */
    private $conn;

    /**
     * @var string
     */
    private $explainSkeleton = 'EOF
+----+-------------+---------+------------+------+---------------+------+---------+------+------+----------+-------+
| id | select_type | table   | partitions | type | possible_keys | key  | key_len | ref  | rows | filtered | Extra |
+----+-------------+---------+------------+------+---------------+------+---------+------+------+----------+-------+
| %s |      %s     |    %s   |     %s     |  %s  |       %s      |  %s  |   %s    |  %s  |  %s  |     %s   |   %s  |
+----+-------------+---------+------------+------+---------------+------+---------+------+------+----------+-------+
EOF';

    /**
     * PDO constructor.
     *
     * @param $dsn
     * @param null  $username
     * @param null  $password
     * @param array $options
     */
    public function __construct(
        $dsn,
        $username = null,
        $password = null,
        array $options = [self::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8']
    ) {
        try {
            parent::__construct($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage());
        }
        $this->conn = new BasePDO($dsn, $username, $password, $options);
    }

    /**
     * @param string $sql
     *
     * @return string
     *
     * @throws \Guanguans\SoarPHP\Exceptions\InvalidArgumentException
     */
    public function getStrExplain(string $sql): string
    {
        $explain = $this->getAllExplain($sql);

        return sprintf(
            $this->getExplainSkeleton(),
            $explain['id'],
            $explain['select_type'],
            $explain['table'],
            $explain['partitions'],
            $explain['type'],
            $explain['possible_keys'],
            $explain['key'],
            $explain['key_len'],
            $explain['ref'],
            $explain['rows'],
            $explain['filtered'],
            $explain['Extra']
        );
    }

    /**
     * @param string $sql
     *
     * @return array
     *
     * @throws \Guanguans\SoarPHP\Exceptions\InvalidArgumentException
     */
    public function getAllExplain(string $sql): array
    {
        if (empty($sql)) {
            throw new PDOException('Sql statement cannot be empty.');
        }

        return array_merge($this->getExplain($sql, 'partitions'), $this->getExplain($sql, 'extended'));
    }

    /**
     * @param string      $sql
     * @param string|null $type
     *
     * @return array
     *
     * @throws \Guanguans\SoarPHP\Exceptions\InvalidArgumentException
     */
    public function getExplain(string $sql, string $type = null): array
    {
        if (null !== $type && !\in_array(\strtolower($type), ['partitions', 'extended'])) {
            throw new InvalidArgumentException('Invalid type value(partitions/extended): '.$type);
        }
        if (false === ($explain = $this->conn->query('EXPLAIN '.$type.' '.$sql, self::FETCH_ASSOC))) {
            throw new PDOException(sprintf('Sql statement error: %s', $sql));
        }

        foreach ($explain as $row) {
            return $row;
        }
    }

    /**
     * @return string
     */
    public function getExplainSkeleton(): string
    {
        return $this->explainSkeleton;
    }

    /**
     * @param string $explainSkeleton
     */
    public function setExplainSkeleton(string $explainSkeleton)
    {
        $this->explainSkeleton = $explainSkeleton;
    }

    /**
     * close PDO.
     */
    public function closeConnection()
    {
        $this->conn = null;
    }
}
