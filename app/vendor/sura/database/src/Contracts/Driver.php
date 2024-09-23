<?php

/*
 * Copyright (c) 2023. Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Contracts;


use Sura\Database\Exception\ConnectionException;

/**
 * Supplemental database driver.
 */
interface Driver
{
	public const
		SupportSequence = 'sequence',
		SupportSelectUngroupedColumns = 'ungrouped_cols',
		SupportMultiInsertAsSelect = 'insert_as_select',
		SupportMultiColumnAsOrCond = 'multi_column_as_or',
		SupportSubselect = 'subselect',
		SupportSchema = 'schema';

	/**
	 * Initializes connection.
	 * @throws ConnectionException
	 */
	function connect(string $dsn, ?string $user = null, ?string $password = null, ?array $options = null): void;

	function query(string $queryString, array $params): ResultDriver;

	function beginTransaction(): void;

	function commit(): void;

	function rollBack(): void;

	/**
	 * Returns the ID of the last inserted row or sequence value.
	 */
	function getInsertId(?string $sequence = null): string;

	/**
	 * Delimits string for use in SQL statement.
	 */
	function quote(string $string): string;

	/**
	 * Delimits identifier for use in SQL statement.
	 */
	function delimite(string $name): string;

	/**
	 * Formats date-time for use in a SQL statement.
	 */
	function formatDateTime(\DateTimeInterface $value): string;

	/**
	 * Formats date-time interval for use in a SQL statement.
	 */
	function formatDateInterval(\DateInterval $value): string;

	/**
	 * Encodes string for use in a LIKE statement.
	 */
	function formatLike(string $value, int $pos): string;

	/**
	 * Injects LIMIT/OFFSET to the SQL query.
	 */
	function applyLimit(string &$sql, ?int $limit, ?int $offset): void;

	/********************* reflection ****************d*g**/

	/**
	 * @return \Sura\Database\Reflection\Table[]
	 */
	function getTables(): array;

	/**
	 * Returns metadata for all columns in a table.
	 * @return \Sura\Database\Reflection\Column[]
	 */
	function getColumns(string $table): array;

	/**
	 * Returns metadata for all indexes in a table.
	 * @return \Sura\Database\Reflection\Index[]
	 */
	function getIndexes(string $table): array;

	/**
	 * Returns metadata for all foreign keys in a table.
	 * @return \Sura\Database\Reflection\ForeignKey[]
	 */
	function getForeignKeys(string $table): array;

	/**
	 * Cheks if driver supports specific property
	 * @param  string  $item  self::SUPPORT_* property
	 */
	function isSupported(string $item): bool;
}


interface_exists(ISupplementalDriver::class);
