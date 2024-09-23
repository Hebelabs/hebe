<?php

/**
 * Copyright (c) 2023 Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database;

use JetBrains\PhpStorm\Language;
use Sura\Database\Contracts\Driver;
use Sura\Database\Exception\ConnectionException;
use Sura\Database\Exception\DriverException;
use Sura\Database\Utils\Arrays;

/**
 * Represents a connection between PHP and a database server.
 */
class Database
{
	/** @var array<callable(self): void>  Occurs after connection is established */
	public array $onConnect = [];

	/** @var array<callable(self, ResultSet|DriverException): void>  Occurs after query is executed */
	public array $onQuery = [];

	private array $params;
	private array $options;
	private ?Driver $driver = null;
	private SqlPreprocessor $preprocessor;

	/** @var callable(array, ResultSet): array */
	private $rowNormalizer;
	private ?string $sql = null;
	private int $transactionDepth = 0;


    /**
     * @throws ConnectionException
     */
    public function __construct(
		string $dsn,
		#[\SensitiveParameter]
		?string $user = null,
		#[\SensitiveParameter]
		?string $password = null,
		?array $options = null,
	) {
		$this->params = [$dsn, $user, $password];
		$this->options = (array) $options;
		$this->rowNormalizer = new RowNormalizer;

		if (empty($options['lazy'])) {
			$this->connect();
		}
	}


    /**
     * @throws ConnectionException
     */
    public function connect(): void
	{
		if ($this->driver) {
			return;
		}

		$dsn = explode(':', $this->params[0])[0];
		$class = empty($this->options['driverClass'])
			? 'Sura\Database\Drivers\\' . ucfirst(str_replace('sql', 'Sql', $dsn)) . 'Driver'
			: $this->options['driverClass'];
		if (!class_exists($class)) {
			throw new ConnectionException("Invalid data source '$dsn'.");
		}

		$this->driver = new $class;
		$this->driver->connect($this->params[0], $this->params[1], $this->params[2], $this->options);
		$this->preprocessor = new SqlPreprocessor($this);
		Arrays::invoke($this->onConnect, $this);
	}


    /**
     * @throws ConnectionException
     */
    public function reconnect(): void
	{
		$this->disconnect();
		$this->connect();
	}


	public function disconnect(): void
	{
		$this->driver = null;
	}


	public function getDsn(): string
	{
		return $this->params[0];
	}


    /** deprecated use getDriver()->getPdo()
     * @throws ConnectionException
     */
	public function getPdo(): \PDO
	{
		$this->connect();
		return $this->driver->getPdo();
	}


    /**
     * @throws ConnectionException
     */
    public function getDriver(): Driver
	{
		$this->connect();
		return $this->driver;
	}

	public function setRowNormalizer(?callable $normalizer): self
	{
		$this->rowNormalizer = $normalizer;
		return $this;
	}


    /**
     * @throws ConnectionException
     */
    public function getInsertId(?string $sequence = null): string
	{
		$this->connect();
		return $this->driver->getInsertId($sequence);
	}


    /**
     * @throws ConnectionException
     */
    public function quote(string $string): string
	{
		$this->connect();
		return $this->driver->quote($string);
	}


    /**
     * @throws DriverException
     */
    public function beginTransaction(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->query('::beginTransaction');
	}


    /**
     * @throws DriverException
     */
    public function commit(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->query('::commit');
	}


    /**
     * @throws DriverException
     */
    public function rollBack(): void
	{
		if ($this->transactionDepth !== 0) {
			throw new \LogicException(__METHOD__ . '() call is forbidden inside a transaction() callback');
		}

		$this->query('::rollBack');
	}


    /**
     * @throws DriverException
     * @throws \Throwable
     */
    public function transaction(callable $callback): mixed
	{
		if ($this->transactionDepth === 0) {
			$this->beginTransaction();
		}

		$this->transactionDepth++;
		try {
			$res = $callback($this);
		} catch (\Throwable $e) {
			$this->transactionDepth--;
			if ($this->transactionDepth === 0) {
				$this->rollback();
			}

			throw $e;
		}

		$this->transactionDepth--;
		if ($this->transactionDepth === 0) {
			$this->commit();
		}

		return $res;
	}


    /**
     * Generates and executes SQL query.
     * @param literal-string $sql
     * @throws ConnectionException
     * @throws DriverException
     */
	public function query(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ResultSet
	{
		[$this->sql, $params] = $this->preprocess($sql, ...$params);
		try {
			$result = new ResultSet($this, $this->sql, $params, $this->rowNormalizer);
		} catch (DriverException $e) {
			Arrays::invoke($this->onQuery, $this, $e);
			throw $e;
		}

		Arrays::invoke($this->onQuery, $this, $result);
		return $result;
	}

    /**
     * @param literal-string $sql
     * @return array{string, array}
     * @throws ConnectionException
     */
	public function preprocess(string $sql, ...$params): array
	{
		$this->connect();
		return $params
			? $this->preprocessor->process(func_get_args())
			: [$sql, []];
	}


	public function getLastQueryString(): ?string
	{
		return $this->sql;
	}


	/********************* shortcuts ****************d*g**/


    /**
     * Shortcut for query()->fetch()
     * @param literal-string $sql
     * @throws DriverException
     */
	public function fetch(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?Row
	{
		return $this->query($sql, ...$params)->fetch();
	}


    /**
     * Shortcut for query()->fetchField()
     * @param literal-string $sql
     * @throws DriverException
     */
	public function fetchField(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): mixed
	{
		return $this->query($sql, ...$params)->fetchField();
	}


    /**
     * Shortcut for query()->fetchFields()
     * @param literal-string $sql
     * @throws DriverException
     */
	public function fetchFields(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): ?array
	{
		return $this->query($sql, ...$params)->fetchFields();
	}


    /**
     * Shortcut for query()->fetchPairs()
     * @param literal-string $sql
     * @throws DriverException
     */
	public function fetchPairs(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): array
	{
		return $this->query($sql, ...$params)->fetchPairs();
	}


    /**
     * Shortcut for query()->fetchAll()
     * @param literal-string $sql
     * @throws DriverException
     */
	public function fetchAll(#[Language('SQL')] string $sql, #[Language('GenericSQL')] ...$params): array
	{
		return $this->query($sql, ...$params)->fetchAll();
	}

    /**
     * @param string $value
     * @param ...$params
     * @return SqlLiteral
     */
	public static function literal(string $value, ...$params): SqlLiteral
	{
		return new SqlLiteral($value, $params);
	}
}
