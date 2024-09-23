<?php

/*
 * Copyright (c) 2023 Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Reflection;


/**
 * Reflection of table or result set column.
 */
final class Column
{
	public function __construct(
		public string $name,
		public string $nativeType = '',
		public ?string $table = null,
		public ?int $size = null,
		public bool $nullable = false,
		public mixed $default = null,
		public bool $autoIncrement = false,
		public bool $primary = false,
		public array $vendor = [],
	) {
	}
}
