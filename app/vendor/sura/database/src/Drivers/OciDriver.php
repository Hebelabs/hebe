<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

use Sura\Database\Exception\DriverException;
use Sura\Database\Exception\ForeignKeyConstraintViolationException;
use Sura\Database\Exception\InvalidArgumentException;
use Sura\Database\Exception\NotImplementedException;
use Sura\Database\Exception\NotNullConstraintViolationException;
use Sura\Database\Exception\NotSupportedException;
use Sura\Database\Exception\UniqueConstraintViolationException;

/**
 * Supplemental Oracle database driver.
 */
class OciDriver extends PdoDriver
{
	/** Datetime format */
	private string $fmtDateTime;

    /**
     * @param string $dsn
     * @param string|null $user
     * @param string|null $password
     * @param array|null $options
     * @return void
     * @throws DriverException
     */
	public function connect(
		string $dsn,
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		?array $options = null,
	): void
	{
		parent::connect($dsn, $user, $password, $options);
		$this->fmtDateTime = $options['formatDateTime'] ?? 'U';
	}

    /**
     * @param \PDOException $e
     * @return string|null
     */
	public function detectExceptionClass(\PDOException $e): ?string
	{
		$code = $e->errorInfo[1] ?? null;
		if (in_array($code, [1, 2299, 38911], strict: true)) {
			return UniqueConstraintViolationException::class;

		} elseif (in_array($code, [1400], strict: true)) {
			return NotNullConstraintViolationException::class;

		} elseif (in_array($code, [2266, 2291, 2292], strict: true)) {
			return ForeignKeyConstraintViolationException::class;

		} else {
			return null;
		}
	}


	/********************* SQL ****************d*g**/

    /**
     * @param string $name
     * @return string
     */
	public function delimite(string $name): string
	{
		// @see http://download.oracle.com/docs/cd/B10500_01/server.920/a96540/sql_elements9a.htm
		return '"' . str_replace('"', '""', $name) . '"';
	}

    /**
     * @param \DateTimeInterface $value
     * @return string
     */
	public function formatDateTime(\DateTimeInterface $value): string
	{
		return $value->format($this->fmtDateTime);
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
		throw new NotImplementedException;
	}

    /**
     * @param string $sql
     * @param int|null $limit
     * @param int|null $offset
     * @return void
     */
	public function applyLimit(string &$sql, ?int $limit, ?int $offset): void
	{
		if ($limit < 0 || $offset < 0) {
			throw new InvalidArgumentException('Negative offset or limit.');

		} elseif ($offset) {
			// see http://www.oracle.com/technology/oramag/oracle/06-sep/o56asktom.html
			$sql = 'SELECT * FROM (SELECT t.*, ROWNUM AS "__rnum" FROM (' . $sql . ') t '
				. ($limit !== null ? 'WHERE ROWNUM <= ' . ($offset + $limit) : '')
				. ') WHERE "__rnum" > ' . $offset;

		} elseif ($limit !== null) {
			$sql = 'SELECT * FROM (' . $sql . ') WHERE ROWNUM <= ' . $limit;
		}
	}


	/********************* reflection ****************d*g**/

    /**
     * @return array|\Sura\Database\Reflection\Table[]
     */
	public function getTables(): array
	{
		$tables = [];
		foreach ($this->pdo->query('SELECT * FROM cat') as $row) {
			if ($row[1] === 'TABLE' || $row[1] === 'VIEW') {
				$tables[] = [
					'name' => $row[0],
					'view' => $row[1] === 'VIEW',
				];
			}
		}

		return $tables;
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
		return $item === self::SupportSequence || $item === self::SupportSubselect;
	}
}
