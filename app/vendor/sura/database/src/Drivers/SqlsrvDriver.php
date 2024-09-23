<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

use Sura\Database\Exception\InvalidArgumentException;
use Sura\Database\Exception\NotSupportedException;
use Sura\Database\Helpers;

/**
 * Supplemental SQL Server 2005 and later database driver.
 */
class SqlsrvDriver extends PdoDriver
{
	private string $version;

    /**
     * @param string $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null $options
     * @return void
     * @throws \Sura\Database\Exception\DriverException
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
		$this->version = $this->pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);
	}


	/********************* SQL ****************d*g**/

    /**
     * @param string $name
     * @return string
     */
	public function delimite(string $name): string
	{
		/** @see https://msdn.microsoft.com/en-us/library/ms176027.aspx */
		return '[' . str_replace(']', ']]', $name) . ']';
	}

    /**
     * @param \DateTimeInterface $value
     * @return string
     */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		/** @see https://msdn.microsoft.com/en-us/library/ms187819.aspx */
		return $value->format("'Y-m-d\\TH:i:s'");
	}

    /**
     * @param \DateInterval $value
     * @return string
     */
	public function formatDateInterval(\DateInterval $value): string
	{
		throw new NotSupportedException;
	}

    /**
     * @param string $value
     * @param int $pos
     * @return string
     */
	public function formatLike(string $value, int $pos): string
	{
		/** @see https://msdn.microsoft.com/en-us/library/ms179859.aspx */
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
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
			throw new InvalidArgumentException('Negative offset or limit.');

		} elseif (version_compare($this->version, '11', '<')) { // 11 == SQL Server 2012
			if ($offset) {
				throw new NotSupportedException('Offset is not supported by this database.');

			} elseif ($limit !== null) {
				$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . $limit, $sql, 1, $count);
				if (!$count) {
					throw new InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
				}
			}
		} elseif ($limit !== null || $offset) {
			// requires ORDER BY, see https://technet.microsoft.com/en-us/library/gg699618(v=sql.110).aspx
			$sql .= ' OFFSET ' . (int) $offset . ' ROWS '
				. 'FETCH NEXT ' . (int) $limit . ' ROWS ONLY';
		}
	}


	/********************* reflection ****************d*g**/

    /**
     * @return array|\Sura\Database\Reflection\Table[]
     */
	public function getTables(): array
	{
		return $this->pdo->query(<<<'X'
			SELECT
				name,
				CASE type
					WHEN 'U' THEN 0
					WHEN 'V' THEN 1
				END
			FROM
				sys.objects
			WHERE
				type IN ('U', 'V')
			X)->fetchAll(
			\PDO::FETCH_FUNC,
			fn($name, $view) => new \Sura\Database\Reflection\Table($name, (bool) $view),
		);
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\Column[]
     */
	public function getColumns(string $table): array
	{
		$columns = [];
		foreach ($this->pdo->query(<<<X
			SELECT
				c.name AS name,
				o.name AS [table],
				t.name AS nativeType,
				NULL AS size,
				c.is_nullable AS nullable,
				OBJECT_DEFINITION(c.default_object_id) AS [default],
				c.is_identity AS autoIncrement,
				CASE WHEN i.index_id IS NULL
					THEN 0
					ELSE 1
				END AS [primary]
			FROM
				sys.columns c
				JOIN sys.objects o ON c.object_id = o.object_id
				LEFT JOIN sys.types t ON c.user_type_id = t.user_type_id
				LEFT JOIN sys.key_constraints k ON o.object_id = k.parent_object_id AND k.type = 'PK'
				LEFT JOIN sys.index_columns i ON k.parent_object_id = i.object_id AND i.index_id = k.unique_index_id AND i.column_id = c.column_id
			WHERE
				o.type IN ('U', 'V')
				AND o.name = {$this->pdo->quote($table)}
			X, \PDO::FETCH_ASSOC) as $row) {
			$row['vendor'] = $row;
			$row['nullable'] = (bool) $row['nullable'];
			$row['autoIncrement'] = (bool) $row['autoIncrement'];
			$row['primary'] = (bool) $row['primary'];

			$columns[] = new \Sura\Database\Reflection\Column(...$row);
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
		foreach ($this->pdo->query(<<<X
			SELECT
				i.name AS name,
				CASE WHEN i.is_unique = 1 OR i.is_unique_constraint = 1
					THEN 1
					ELSE 0
				END AS [unique],
				i.is_primary_key AS [primary],
				c.name AS [column]
			FROM
				sys.indexes i
				JOIN sys.index_columns ic ON i.object_id = ic.object_id AND i.index_id = ic.index_id
				JOIN sys.columns c ON ic.object_id = c.object_id AND ic.column_id = c.column_id
				JOIN sys.tables t ON i.object_id = t.object_id
			WHERE
				t.name = {$this->pdo->quote($table)}
			ORDER BY
				i.index_id,
				ic.index_column_id
			X) as $row) {
			$id = $row['name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = (bool) $row['unique'];
			$indexes[$id]['primary'] = (bool) $row['primary'];
			$indexes[$id]['columns'][] = $row['column'];
		}

		return array_map(fn($data) => new \Sura\Database\Reflection\Index(...$data), array_values($indexes));
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\ForeignKey[]
     */
	public function getForeignKeys(string $table): array
	{
		// Does't work with multicolumn foreign keys
		$keys = [];
		foreach ($this->pdo->query(<<<X
			SELECT
				fk.name AS name,
				cl.name AS local,
				tf.name AS [table],
				cf.name AS [column]
			FROM
				sys.foreign_keys fk
				JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
				JOIN sys.tables tl ON fkc.parent_object_id = tl.object_id
				JOIN sys.columns cl ON fkc.parent_object_id = cl.object_id AND fkc.parent_column_id = cl.column_id
				JOIN sys.tables tf ON fkc.referenced_object_id = tf.object_id
				JOIN sys.columns cf ON fkc.referenced_object_id = cf.object_id AND fkc.referenced_column_id = cf.column_id
			WHERE
				tl.name = {$this->pdo->quote($table)}
			X, \PDO::FETCH_ASSOC) as $row) {
			$id = $row['name'];
			$keys[$id]['name'] = $id;
			$keys[$id]['columns'][] = $row['local'];
			$keys[$id]['targetTable'] = $row['table'];
			$keys[$id]['targetColumns'][] = $row['column'];
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
			if (
				isset($meta['sqlsrv:decl_type'])
				&& $meta['sqlsrv:decl_type'] !== 'timestamp'
			) { // timestamp does not mean time in sqlsrv
				$types[$meta['name']] = Helpers::detectType($meta['sqlsrv:decl_type']);
			} elseif (isset($meta['native_type'])) {
				$types[$meta['name']] = Helpers::detectType($meta['native_type']);
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
		return $item === self::SupportSubselect;
	}
}
