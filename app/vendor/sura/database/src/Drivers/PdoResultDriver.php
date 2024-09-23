<?php

/*
 * Copyright (c) 2004-$today.year.Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Drivers;

/**
 * PDO-based result-set driver.
 */
class PdoResultDriver implements \Sura\Database\Contracts\ResultDriver
{
	private \PDOStatement $result;

	private PdoDriver $driver;

    /**
     * @param \PDOStatement $result
     * @param PdoDriver $driver
     */
	public function __construct(\PDOStatement $result, PdoDriver $driver)
	{
		$this->result = $result;
		$this->driver = $driver;
	}

    /**
     * @return array|null
     */
	public function fetch(): ?array
	{
		$data = $this->result->fetch();
		if (!$data) {
			$this->result->closeCursor();
			return null;
		}

		return $data;
	}

    /**
     * @return int
     */
	public function getColumnCount(): int
	{
		return $this->result->columnCount();
	}

    /**
     * @return int
     */
	public function getRowCount(): int
	{
		return $this->result->rowCount();
	}

    /**
     * @return array
     */
	public function getColumnTypes(): array
	{
		return $this->driver->getColumnTypes($this->result);
	}

    /**
     * @param int $col
     * @return array
     */
	public function getColumnMeta(int $col): array
	{
		return $this->result->getColumnMeta($col);
	}

    /**
     * @return \PDOStatement
     */
	public function getPdoStatement(): \PDOStatement
	{
		return $this->result;
	}
}
