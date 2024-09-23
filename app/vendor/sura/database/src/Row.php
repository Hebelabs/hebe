<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database;

use Sura\Database\Contracts\IRow;
use Sura\Database\Exception\MemberAccessException;

/**
 * Represents a single table row.
 */
class Row extends \Sura\Database\Utils\ArrayHash implements IRow
{
	public function __get($key)
	{
		$hint = \Sura\Database\Utils\Helpers::getSuggestion(array_map('strval', array_keys((array) $this)), $key);
		throw new MemberAccessException("Cannot read an undeclared column '$key'" . ($hint ? ", did you mean '$hint'?" : '.'));
	}


	public function __isset($key)
	{
		return isset($this->key);
	}


	/**
	 * Returns a item.
	 * @param  string|int  $key  key or index
	 */
	public function offsetGet($key): mixed
	{
		if (is_int($key)) {
			$arr = array_slice((array) $this, $key, 1);
			if (!$arr) {
				throw new MemberAccessException("Cannot read an undeclared column '$key'.");
			}

			return current($arr);
		}

		return $this->$key;
	}


	/**
	 * Checks if $key exists.
	 * @param  string|int  $key  key or index
	 */
	public function offsetExists($key): bool
	{
		if (is_int($key)) {
			return (bool) current(array_slice((array) $this, $key, 1));
		}

		return parent::offsetExists($key);
	}
}
