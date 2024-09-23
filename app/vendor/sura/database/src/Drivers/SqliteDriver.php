<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

use Sura\Database\Exception\ConstraintViolationException;
use Sura\Database\Exception\ForeignKeyConstraintViolationException;
use Sura\Database\Exception\InvalidArgumentException;
use Sura\Database\Exception\NotNullConstraintViolationException;
use Sura\Database\Exception\NotSupportedException;
use Sura\Database\Exception\UniqueConstraintViolationException;

/**
 * Supplemental SQLite3 database driver.
 */
class SqliteDriver extends PdoDriver
{
	/** Datetime format */
	private string $fmtDateTime;

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
		$this->fmtDateTime = $options['formatDateTime'] ?? 'U';
	}

    /**
     * @param \PDOException $e
     * @return string|null
     */
	public function detectExceptionClass(\PDOException $e): ?string
	{
		$code = $e->errorInfo[1] ?? null;
		$msg = $e->getMessage();
		if ($code !== 19) {
			return null;

		} elseif (
			str_contains($msg, 'must be unique')
			|| str_contains($msg, 'is not unique')
			|| str_contains($msg, 'UNIQUE constraint failed')
		) {
			return UniqueConstraintViolationException::class;

		} elseif (
			str_contains($msg, 'may not be null')
			|| str_contains($msg, 'NOT NULL constraint failed')
		) {
			return NotNullConstraintViolationException::class;

		} elseif (
			str_contains($msg, 'foreign key constraint failed')
			|| str_contains($msg, 'FOREIGN KEY constraint failed')
		) {
			return ForeignKeyConstraintViolationException::class;

		} else {
			return ConstraintViolationException::class;
		}
	}


	/********************* SQL ****************d*g**/

    /**
     * @param string $name
     * @return string
     */
	public function delimite(string $name): string
	{
		return '[' . strtr($name, '[]', '  ') . ']';
	}

    /**
     * @param \DateTimeInterface $value
     * @return string
     */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format($this->fmtDateTime);
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
		$value = addcslashes(substr($this->pdo->quote($value), 1, -1), '%_\\');
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'") . " ESCAPE '\\'";
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

		} elseif ($limit !== null || $offset) {
			$sql .= ' LIMIT ' . ($limit ?? '-1')
				. ($offset ? ' OFFSET ' . $offset : '');
		}
	}


	/********************* reflection ****************d*g**/

    /**
     * @return array|\Sura\Database\Reflection\Table[]
     */
	public function getTables(): array
	{
		return $this->pdo->query(<<<'X'
			SELECT name, type = 'view'
			FROM sqlite_master
			WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'
			UNION ALL
			SELECT name, type = 'view' as view
			FROM sqlite_temp_master
			WHERE type IN ('table', 'view') AND name NOT LIKE 'sqlite_%'
			ORDER BY name
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
		$meta = $this->pdo->query(<<<X
			SELECT sql
			FROM sqlite_master
			WHERE type = 'table' AND name = {$this->pdo->quote($table)}
			UNION ALL
			SELECT sql
			FROM sqlite_temp_master
			WHERE type = 'table' AND name = {$this->pdo->quote($table)}
			X)->fetch();

		$columns = [];
		foreach ($this->pdo->query("PRAGMA table_info({$this->delimite($table)})", \PDO::FETCH_ASSOC) as $row) {
			$column = $row['name'];
			$pattern = "/(\"$column\"|`$column`|\\[$column\\]|$column)\\s+[^,]+\\s+PRIMARY\\s+KEY\\s+AUTOINCREMENT/Ui";
			$type = explode('(', $row['type']);
			$columns[] = new \Sura\Database\Reflection\Column(
				name: $column,
				table: $table,
				nativeType: $type[0],
				size: isset($type[1]) ? (int) $type[1] : null,
				nullable: !$row['notnull'],
				default: $row['dflt_value'],
				autoIncrement: $meta && preg_match($pattern, (string) $meta['sql']),
				primary: $row['pk'] > 0,
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
		foreach ($this->pdo->query("PRAGMA index_list({$this->delimite($table)})") as $row) {
			$id = $row['name'];
			$indexes[$id]['name'] = $id;
			$indexes[$id]['unique'] = (bool) $row['unique'];
			$indexes[$id]['primary'] = false;
		}

		foreach ($indexes as $index => $values) {
			foreach ($this->pdo->query("PRAGMA index_info({$this->delimite($index)})") as $row) {
				$indexes[$index]['columns'][] = $row['name'];
			}
		}

		$columns = $this->getColumns($table);
		foreach ($indexes as $index => $values) {
			$column = $indexes[$index]['columns'][0];
			foreach ($columns as $info) {
				if ($column === $info->name) {
					$indexes[$index]['primary'] = $info->primary;
					break;
				}
			}
		}

		if (!$indexes) { // @see http://www.sqlite.org/lang_createtable.html#rowid
			foreach ($columns as $column) {
				if ($column['vendor']['pk']) {
					$indexes[] = [
						'name' => 'ROWID',
						'unique' => true,
						'primary' => true,
						'columns' => [$column['name']],
					];
					break;
				}
			}
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
		foreach ($this->pdo->query("PRAGMA foreign_key_list({$this->delimite($table)})") as $row) {
			$id = $row['id'];
			$keys[$id]['name'] = (string) $id;
			$keys[$id]['columns'][] = $row['from'];
			$keys[$id]['targetTable'] = $row['table'];
			$keys[$id]['targetColumns'][] = $row['to'];
			if ($keys[$id]['targetColumns'][0] == null) {
				$keys[$id]['targetColumns'] = [];
			}
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
			if (isset($meta['sqlite:decl_type'])) {
				$types[$meta['name']] = in_array($meta['sqlite:decl_type'], ['DATE', 'DATETIME'], strict: true)
					? \Sura\Database\Contracts\IStructure::FIELD_UNIX_TIMESTAMP
					: \Sura\Database\Helpers::detectType($meta['sqlite:decl_type']);
			} elseif (isset($meta['native_type'])) {
				$types[$meta['name']] = \Sura\Database\Helpers::detectType($meta['native_type']);
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
		return $item === self::SupportMultiInsertAsSelect || $item === self::SupportSubselect || $item === self::SupportMultiColumnAsOrCond;
	}
}
