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
 * Supplemental database driver for result-set.
 */
interface ResultDriver
{
	/**
	 * Fetches the row at current position and moves the internal cursor to the next position.
	 */
	function fetch(): ?array;

	/**
	 * Returns the number of columns in a result set.
	 */
	function getColumnCount(): int;

	/**
	 * Returns the number of rows in a result set.
	 */
	function getRowCount(): int;

	/**
	 * Returns associative array of detected types (IStructure::FIELD_*) in result set.
	 */
	function getColumnTypes(): array;

	/**
	 * Returns associative array of original table names.
	 */
	function getColumnMeta(int $col): array;
}
