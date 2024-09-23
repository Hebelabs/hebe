<?php

/**
 * Copyright (c) 2023 Sura
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database;


/**
 * Date Time.
 */
final class DateTime extends \DateTimeImmutable implements \JsonSerializable
{
	public function __construct(string|int $time = 'now')
	{
		if (is_numeric($time)) {
			$time = (new self('@' . $time))
				->setTimezone(new \DateTimeZone(date_default_timezone_get()))
				->format('Y-m-d H:i:s.u');
		}

		parent::__construct($time);
	}


	/**
	 * Returns JSON representation in ISO 8601 (used by JavaScript).
	 */
	public function jsonSerialize(): string
	{
		return $this->format('c');
	}


	/**
	 * Returns the date and time in the format 'Y-m-d H:i:s.u'.
	 */
	public function __toString(): string
	{
		return $this->format('Y-m-d H:i:s.u');
	}
}
