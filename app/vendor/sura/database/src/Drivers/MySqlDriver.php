<?php

/*
 * Copyright (c) 2023.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

use Sura\Database\Exception\ConnectionException;
use Sura\Database\Exception\ForeignKeyConstraintViolationException;
use Sura\Database\Exception\NotNullConstraintViolationException;
use Sura\Database\Exception\UniqueConstraintViolationException;

/**
 * Supplemental MySQL database driver.
 */
class MySqlDriver extends PdoDriver
{
	public const
		ERROR_ACCESS_DENIED = 1045,
		ERROR_DUPLICATE_ENTRY = 1062,
		ERROR_DATA_TRUNCATED = 1265;


	/**
	 * Driver options:
	 *   - charset => character encoding to set (default is utf8 or utf8mb4 since MySQL 5.5.3)
	 *   - sqlmode => see http://dev.mysql.com/doc/refman/5.0/en/server-sql-mode.html
	 */
	public function connect(
		string $dsn,
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		?array $options = null,
	): void
	{
		parent::connect($dsn, $user, $password, $options);
		$charset = $options['charset']
			?? (version_compare($this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), '5.5.3', '>=') ? 'utf8mb4' : 'utf8');
		if ($charset) {
			$this->pdo->query('SET NAMES ' . $this->pdo->quote($charset));
		}

		if (isset($options['sqlmode'])) {
			$this->pdo->query('SET sql_mode=' . $this->pdo->quote($options['sqlmode']));
		}
	}

    /**
     * @param \PDOException $e
     * @return string|null
     */
	public function detectExceptionClass(\PDOException $e): ?string
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1216, 1217, 1451, 1452, 1701], strict: true)) {
			return ForeignKeyConstraintViolationException::class;

		} elseif (in_array($code, [1062, 1557, 1569, 1586], strict: true)) {
			return UniqueConstraintViolationException::class;

		} elseif ($code >= 2001 && $code <= 2028) {
			return ConnectionException::class;

		} elseif (in_array($code, [1048, 1121, 1138, 1171, 1252, 1263, 1566], strict: true)) {
			return NotNullConstraintViolationException::class;

		} else {
			return null;
		}
	}


	/********************* SQL ****************d*g**/

    /**
     * @param string $name
     * @return string
     */
	public function delimite(string $name): string
	{
		// @see http://dev.mysql.com/doc/refman/5.0/en/identifiers.html
		return '`' . str_replace('`', '``', $name) . '`';
	}

    /**
     * @param \DateTimeInterface $value
     * @return string
     */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format("'Y-m-d H:i:s'");
	}

    /**
     * @param \DateInterval $value
     * @return string
     */
	public function formatDateInterval(\DateInterval $value): string
	{
		return $value->format("'%r%h:%I:%S'");
	}

    /**
     * @param string $value
     * @param int $pos
     * @return string
     */
	public function formatLike(string $value, int $pos): string
	{
		$value = str_replace('\\', '\\\\', $value);
		$value = addcslashes(substr($this->pdo->quote($value), 1, -1), '%_');
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}

    /**
     * @param string $sql
     * @param int|null $limit
     * @param int|null $offset
     * @return void
     */
	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new \Sura\Database\Exception\InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null || $offset) {
			// see http://dev.mysql.com/doc/refman/5.0/en/select.html
			$sql .= ' LIMIT ' . ($limit ?? '18446744073709551615')
				. ($offset ? ' OFFSET ' . $offset : '');
		}
	}


	/********************* reflection ****************d*g**/

    /**
     * @return array|\Sura\Database\Reflection\Table[]
     */
	public function getTables(): array
	{
		return $this->pdo->query('SHOW FULL TABLES')->fetchAll(
			\PDO::FETCH_FUNC,
			fn($name, $type) => new \Sura\Database\Reflection\Table($name, $type === 'VIEW'),
		);
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\Column[]
     */
	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->pdo->query('SHOW FULL COLUMNS FROM ' . $this->delimite($table), \PDO::FETCH_ASSOC) as $row) {
			$type = explode('(', $row['Type']);
			$columns[] = new \Sura\Database\Reflection\Column(
				name: $row['Field'],
				table: $table,
				nativeType: $type[0],
				size: isset($type[1]) ? (int) $type[1] : null,
				nullable: $row['Null'] === 'YES',
				default: $row['Default'],
				autoIncrement: $row['Extra'] === 'auto_increment',
				primary: $row['Key'] === 'PRI',
				vendor: $row,
			);
		}

		return $columns;
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\Index[]
     */
	public function getIndexes(string $table): array
	{
		$indexes = [];
		foreach ($this->pdo->query('SHOW INDEX FROM ' . $this->delimite($table)) as $row) {
			$id = $row['Key_name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = !$row['Non_unique'];
			$indexes[$id]['primary'] = $row['Key_name'] === 'PRIMARY';
			$indexes[$id]['columns'][$row['Seq_in_index'] - 1] = $row['Column_name'];
		}

		return array_map(fn($data) => new \Sura\Database\Reflection\Index(...$data), array_values($indexes));
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\ForeignKey[]
     */
	public function getForeignKeys(string $table): array
	{
		$keys = [];
		foreach ($this->pdo->query(<<<X
			SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = DATABASE()
			  AND REFERENCED_TABLE_NAME IS NOT NULL
			  AND TABLE_NAME = {$this->pdo->quote($table)}
			X) as $row) {
			$id = $row['CONSTRAINT_NAME'];
			$keys[$id]['name'] = $id;
			$keys[$id]['columns'][] = $row['COLUMN_NAME'];
			$keys[$id]['targetTable'] = $row['REFERENCED_TABLE_NAME'];
			$keys[$id]['targetColumns'][] = $row['REFERENCED_COLUMN_NAME'];
		}

		return array_map(fn($data) => new \Sura\Database\Reflection\ForeignKey(...$data), array_values($keys));
	}

    /**
     * @param \PDOStatement $statement
     * @return array
     */
	public function getColumnTypes(\PDOStatement $statement): array
	{
		$types = [];
		$count = $statement->columnCount();
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (isset($meta['native_type'])) {
				$types[$meta['name']] = $type = \Sura\Database\Helpers::detectType($meta['native_type']);
				if ($type === \Sura\Database\Contracts\IStructure::FIELD_TIME) {
					$types[$meta['name']] = \Sura\Database\Contracts\IStructure::FIELD_TIME_INTERVAL;
				}
			}
		}

		return $types;
	}

    /**
     * @param string $item
     * @return bool
     */
	public function isSupported(string $item): bool
	{
		// MULTI_COLUMN_AS_OR_COND due to mysql bugs:
		// - http://bugs.mysql.com/bug.php?id=31188
		// - http://bugs.mysql.com/bug.php?id=35819
		// and more.
		return $item === self::SupportSelectUngroupedColumns || $item === self::SupportMultiColumnAsOrCond;
	}
}
