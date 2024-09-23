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
 * Reflection or index.
 */
final class Index
{
	public function __construct(
		public string $name,
		public array $columns,
		public bool $unique = false,
		public bool $primary = false,
	) {
	}
}
