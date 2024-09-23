<?php

/*
 * Copyright (c) 2023. Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Contracts;


/**
 * Provides cached reflection for database structure.
 */
interface IStructure
{
	public const
		FIELD_TEXT = 'string',
		FIELD_BINARY = 'bin',
		FIELD_BOOL = 'bool',
		FIELD_INTEGER = 'int',
		FIELD_FLOAT = 'float',
		FIELD_FIXED = 'fixed',
		FIELD_DATE = 'date',
		FIELD_TIME = 'time',
		FIELD_DATETIME = 'datetime',
		FIELD_UNIX_TIMESTAMP = 'timestamp',
		FIELD_TIME_INTERVAL = 'timeint';

	/**
	 * Returns tables list.
	 */
	function getTables(): array;

	/**
	 * Returns table columns list.
	 */
	function getColumns(string $table): array;

	/**
	 * Returns table primary key.
	 * @return string|string[]|null
	 */
	function getPrimaryKey(string $table): string|array|null;

	/**
	 * Returns autoincrement primary key name.
	 */
	function getPrimaryAutoincrementKey(string $table): ?string;

	/**
	 * Returns table primary key sequence.
	 */
	function getPrimaryKeySequence(string $table): ?string;

	/**
	 * Returns hasMany reference.
	 * If a targetTable is not provided, returns references for all tables.
	 */
	function getHasManyReference(string $table, ?string $targetTable = null): ?array;

	/**
	 * Returns belongsTo reference.
	 * If a column is not provided, returns references for all columns.
	 */
	function getBelongsToReference(string $table, ?string $column = null): ?array;

	/**
	 * Rebuilds database structure cache.
	 */
	function rebuild(): void;

	/**
	 * Returns true if database cached structure has been rebuilt.
	 */
	function isRebuilt(): bool;
}
