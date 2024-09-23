<?php

/*
 * Copyright (c) 2023 Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database;


/**
 * SQL literal value.
 */
class SqlLiteral
{
	private string $value;

	private array $parameters;


	public function __construct(string $value, array $parameters = [])
	{
		$this->value = $value;
		$this->parameters = $parameters;
	}


	public function getParameters(): array
	{
		return $this->parameters;
	}


	public function __toString(): string
	{
		return $this->value;
	}
}
