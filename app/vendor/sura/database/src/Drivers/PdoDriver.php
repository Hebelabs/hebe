<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

use PDO;
use PDOException;
use Sura\Database\Exception\ConnectionException;
use Sura\Database\Exception\DriverException;
use Sura\Database\Exception\QueryException;


/**
 * PDO-based driver.
 */
abstract class PdoDriver implements \Sura\Database\Contracts\Driver
{
	protected ?PDO $pdo = null;

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
		try {
			$this->pdo = new PDO($dsn, $user, $password, $options);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			throw $this->convertException($e, ConnectionException::class);
		}
	}

    /**
     * @return PDO|null
     */
	public function getPdo(): ?PDO
	{
		return $this->pdo;
	}

    /**
     * @param string $queryString
     * @param array $params
     * @return PdoResultDriver
     * @throws DriverException
     */
	public function query(string $queryString, array $params): PdoResultDriver
	{
		try {
			$types = ['boolean' => PDO::PARAM_BOOL, 'integer' => PDO::PARAM_INT, 'resource' => PDO::PARAM_LOB, 'NULL' => PDO::PARAM_NULL];

			$statement = $this->pdo->prepare($queryString);
			foreach ($params as $key => $value) {
				$type = gettype($value);
				$statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $types[$type] ?? PDO::PARAM_STR);
			}

			$statement->setFetchMode(PDO::FETCH_ASSOC);
			@$statement->execute(); // @ PHP generates warning when ATTR_ERRMODE = ERRMODE_EXCEPTION bug #73878
			return new PdoResultDriver($statement, $this);

		} catch (PDOException $e) {
			$e = $this->convertException($e, QueryException::class);
			$e->setQueryInfo($queryString, $params);
			throw $e;
		}
	}

    /**
     * @return void
     * @throws DriverException
     */
	public function beginTransaction(): void
	{
		try {
			$this->pdo->beginTransaction();
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}

    /**
     * @return void
     * @throws DriverException
     */
	public function commit(): void
	{
		try {
			$this->pdo->commit();
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}

    /**
     * @return void
     * @throws DriverException
     */
	public function rollBack(): void
	{
		try {
			$this->pdo->rollBack();
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}

    /**
     * @param string|null $sequence
     * @return string
     * @throws DriverException
     */
	public function getInsertId(?string $sequence = null): string
	{
		try {
			$res = $this->pdo->lastInsertId($sequence);
			return $res === false ? '0' : $res;
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}

    /**
     * @param string $string
     * @param int $type
     * @return string
     * @throws DriverException
     */
	public function quote(string $string, int $type = PDO::PARAM_STR): string
	{
		try {
			return $this->pdo->quote($string, $type);
		} catch (PDOException $e) {
			throw $this->convertException($e);
		}
	}

    /**
     * @param PDOException $src
     * @param string|null $class
     * @return DriverException
     */
	public function convertException(\PDOException $src, ?string $class = null): DriverException
	{
		if ($src->errorInfo) {
			[$sqlState, $driverCode] = $src->errorInfo;
		} elseif (preg_match('#SQLSTATE\[(.*?)\] \[(.*?)\] (.*)#A', $src->getMessage(), $m)) {
			[, $sqlState, $driverCode] = $m;
		}

		$class = $this->detectExceptionClass($src) ?? $class ?? DriverException::class;
		$e = new $class($src->getMessage(), $sqlState ?? $src->getCode(), $src);
		if (isset($sqlState)) {
			$e->setDriverCode($sqlState, (int) $driverCode);
		}

		return $e;
	}

    /**
     * @param PDOException $e
     * @return string|null
     */
	public function detectExceptionClass(\PDOException $e): ?string
	{
		return null;
	}
}
