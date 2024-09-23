<?php

/*
 * Copyright (c) 2023 Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database;

use Sura\Database\Contracts\IStructure;
use Sura\Database\Contracts\ResultDriver;
use Sura\Database\Exception\ConnectionException;
use Sura\Database\Exception\FileNotFoundException;

/**
 * Database helpers.
 */
class Helpers
{
	use \Sura\Database\Utils\StaticClass;

	/** maximum SQL length */
	public static int $maxLength = 100;

	public static array $typePatterns = [
		'^_' => IStructure::FIELD_TEXT, // PostgreSQL arrays
		'(TINY|SMALL|SHORT|MEDIUM|BIG|LONG)(INT)?|INT(EGER|\d+| IDENTITY)?|(SMALL|BIG|)SERIAL\d*|COUNTER|YEAR|BYTE|LONGLONG|UNSIGNED BIG INT' => IStructure::FIELD_INTEGER,
		'(NEW)?DEC(IMAL)?(\(.*)?|NUMERIC|(SMALL)?MONEY|CURRENCY|NUMBER' => IStructure::FIELD_FIXED,
		'REAL|DOUBLE( PRECISION)?|FLOAT\d*' => IStructure::FIELD_FLOAT,
		'BOOL(EAN)?' => IStructure::FIELD_BOOL,
		'TIME' => IStructure::FIELD_TIME,
		'DATE' => IStructure::FIELD_DATE,
		'(SMALL)?DATETIME(OFFSET)?\d*|TIME(STAMP.*)?' => IStructure::FIELD_DATETIME,
		'BYTEA|(TINY|MEDIUM|LONG|)BLOB|(LONG )?(VAR)?BINARY|IMAGE' => IStructure::FIELD_BINARY,
	];


	/**
	 * Displays complete result set as HTML table for debug purposes.
	 */
	public static function dumpResult(ResultSet $result): void
	{
		echo "\n<table class=\"dump\">\n<caption>" . htmlspecialchars($result->getQueryString(), ENT_IGNORE, 'UTF-8') . "</caption>\n";
		if (!$result->getColumnCount()) {
			echo "\t<tr>\n\t\t<th>Affected rows:</th>\n\t\t<td>", $result->getRowCount(), "</td>\n\t</tr>\n</table>\n";
			return;
		}

		$i = 0;
		foreach ($result as $row) {
			if ($i === 0) {
				echo "<thead>\n\t<tr>\n\t\t<th>#row</th>\n";
				foreach ($row as $col => $foo) {
					echo "\t\t<th>" . htmlspecialchars($col, ENT_NOQUOTES, 'UTF-8') . "</th>\n";
				}

				echo "\t</tr>\n</thead>\n<tbody>\n";
			}

			echo "\t<tr>\n\t\t<th>", $i, "</th>\n";
			foreach ($row as $col) {
				if (is_bool($col)) {
					$s = $col ? 'TRUE' : 'FALSE';
				} elseif ($col === null) {
					$s = 'NULL';
				} else {
					$s = (string) $col;
				}

				echo "\t\t<td>", htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8'), "</td>\n";
			}

			echo "\t</tr>\n";
			$i++;
		}

		if ($i === 0) {
			echo "\t<tr>\n\t\t<td><em>empty result set</em></td>\n\t</tr>\n</table>\n";
		} else {
			echo "</tbody>\n</table>\n";
		}
	}

    /**
	 * Common column type detection.
	 */
	public static function detectTypes(\PDOStatement $statement): array
	{
		$types = [];
		$count = $statement->columnCount(); // driver must be meta-aware, see PHP bugs #53782, #54695
		for ($col = 0; $col < $count; $col++) {
			$meta = $statement->getColumnMeta($col);
			if (isset($meta['native_type'])) {
				$types[$meta['name']] = self::detectType($meta['native_type']);
			}
		}

		return $types;
	}


	/**
	 * Heuristic column type detection.
	 * @internal
	 */
	public static function detectType(string $type): string
	{
		static $cache;
		if (!isset($cache[$type])) {
			$cache[$type] = 'string';
			foreach (self::$typePatterns as $s => $val) {
				if (preg_match("#^($s)$#i", $type)) {
					return $cache[$type] = $val;
				}
			}
		}

		return $cache[$type];
	}

    /**
     * Import SQL dump from file - extremely fast.
     * @param Database $connection
     * @param string $file
     * @param  array<callable(int, ?float): void>  $onProgress
     * @return int  count of commands
     * @throws ConnectionException
     */
	public static function loadFromFile(Database $connection, string $file, ?callable $onProgress = null): int
	{
		@set_time_limit(0); // @ function may be disabled

		$handle = @fopen($file, 'r'); // @ is escalated to exception
		if (!$handle) {
			throw new FileNotFoundException("Cannot open file '$file'.");
		}

		$stat = fstat($handle);
		$count = $size = 0;
		$delimiter = ';';
		$sql = '';
		$pdo = $connection->getPdo(); // native query without logging
		while (($s = fgets($handle)) !== false) {
			$size += strlen($s);
			if (!strncasecmp($s, 'DELIMITER ', 10)) {
				$delimiter = trim(substr($s, 10));

			} elseif (str_ends_with($ts = rtrim($s), $delimiter)) {
				$sql .= substr($ts, 0, -strlen($delimiter));
				$pdo->exec($sql);
				$sql = '';
				$count++;
				if ($onProgress) {
					$onProgress($count, isset($stat['size']) ? $size * 100 / $stat['size'] : null);
				}
			} else {
				$sql .= $s;
			}
		}

		if (rtrim($sql) !== '') {
			$pdo->exec($sql);
			$count++;
			if ($onProgress) {
				$onProgress($count, isset($stat['size']) ? 100 : null);
			}
		}

		fclose($handle);
		return $count;
	}


    /**
	 * Reformat source to key -> value pairs.
	 */
	public static function toPairs(array $rows, $key = null, $value = null): array
	{
		if (!$rows) {
			return [];
		}

		$keys = array_keys((array) reset($rows));
		if (!count($keys)) {
			throw new \LogicException('Result set does not contain any column.');

		} elseif ($key === null && $value === null) {
			if (count($keys) === 1) {
				[$value] = $keys;
			} else {
				[$key, $value] = $keys;
			}
		}

		$return = [];
		if ($key === null) {
			foreach ($rows as $row) {
				$return[] = ($value === null ? $row : $row[$value]);
			}
		} else {
			foreach ($rows as $row) {
				$return[(string) $row[$key]] = ($value === null ? $row : $row[$value]);
			}
		}

		return $return;
	}


	/**
	 * Finds duplicate columns in select statement
	 */
	public static function findDuplicates(ResultDriver $result): string
	{
		$cols = [];
		for ($i = 0; $i < $result->getColumnCount(); $i++) {
			$meta = $result->getColumnMeta($i);
			$cols[$meta['name']][] = $meta['table'] ?? '';
		}

		$duplicates = [];
		foreach ($cols as $name => $tables) {
			if (count($tables) > 1) {
				$tables = array_filter(array_unique($tables));
				$duplicates[] = "'$name'" . ($tables ? ' (from ' . implode(', ', $tables) . ')' : '');
			}
		}

		return implode(', ', $duplicates);
	}
}
