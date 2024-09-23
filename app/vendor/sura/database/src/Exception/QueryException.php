<?php

/*
 * Copyright (c) 2023. Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Exception;


class QueryException extends DriverException
{
	public string $queryString;
	private array $params;


	/** @internal */
	public function setQueryInfo(string $queryString, array $params): void
	{
		$this->queryString = $queryString;
		$this->params = $params;
	}


	public function getQueryString(): string
	{
		return $this->queryString;
	}


	public function getParameters(): array
	{
		return $this->params;
	}
}
