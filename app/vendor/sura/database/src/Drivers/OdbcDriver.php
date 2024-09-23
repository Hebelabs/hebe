<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

use Sura\Database\Exception\InvalidArgumentException;
use Sura\Database\Exception\NotImplementedException;
use Sura\Database\Exception\NotSupportedException;

/**
 * Supplemental ODBC database driver.
 */
class OdbcDriver extends PdoDriver
{
    /**
     * @param string $name
     * @return string
     */
	public function delimite(string $name): string
	{
		return '[' . str_replace(['[', ']'], ['[[', ']]'], $name) . ']';
	}

    /**
     * @param \DateTimeInterface $value
     * @return string
     */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format('#m/d/Y H:i:s#');
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
		$value = strtr($value, ["'" => "''", '%' => '[%]', '_' => '[_]', '[' => '[[]']);
		return ($pos <= 0 ? "'%" : "'") . $value . ($pos >= 0 ? "%'" : "'");
	}

    /**
     * @param string $sql
     * @param int|null $limit
     * @param int|null $offset
     * @return void
     */
	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($offset) {
			throw new NotSupportedException('Offset is not supported by this database.');

		} elseif ($limit < 0) {
			throw new InvalidArgumentException('Negative offset or limit.');

		} elseif ($limit !== null) {
			$sql = preg_replace('#^\s*(SELECT(\s+DISTINCT|\s+ALL)?|UPDATE|DELETE)#i', '$0 TOP ' . $limit, $sql, 1, $count);
			if (!$count) {
				throw new InvalidArgumentException('SQL query must begin with SELECT, UPDATE or DELETE command.');
			}
		}
	}


	/********************* reflection ****************d*g**/

    /**
     * @return array|\Sura\Database\Reflection\Table[]
     */
	public function getTables(): array
	{
		throw new NotImplementedException;
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\Column[]
     */
	public function getColumns(string $table): array
	{
		throw new NotImplementedException;
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\Index[]
     */
	public function getIndexes(string $table): array
	{
		throw new NotImplementedException;
	}

    /**
     * @param string $table
     * @return array|\Sura\Database\Reflection\ForeignKey[]
     */
	public function getForeignKeys(string $table): array
	{
		throw new NotImplementedException;
	}

    /**
     * @param \PDOStatement $statement
     * @return array
     */
	public function getColumnTypes(\PDOStatement $statement): array
	{
		return [];
	}

    /**
     * @param string $item
     * @return bool
     */
	public function isSupported(string $item): bool
	{
		return $item === self::SupportSubselect;
	}
}
