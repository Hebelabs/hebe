<?php

/*
 * Copyright (c) 2023 Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Contracts;

use Sura\Database\Exception\AmbiguousReferenceKeyException;


interface Conventions
{
	/**
	 * Returns primary key for table.
	 */
	function getPrimary(string $table): string|array|null;

	/**
	 * Returns referenced table & referenced column.
	 * Example:
	 *     (author, book) returns [book, author_id]
	 *
	 * @return array|null   [referenced table, referenced column]
	 * @throws AmbiguousReferenceKeyException
	 */
	function getHasManyReference(string $table, string $key): ?array;

	/**
	 * Returns referenced table & referencing column.
	 * Example
	 *     (book, author)      returns [author, author_id]
	 *     (book, translator)  returns [author, translator_id]
	 *
	 * @return array|null   [referenced table, referencing column]
	 */
	function getBelongsToReference(string $table, string $key): ?array;
}
