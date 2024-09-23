<?php

/*
 * Copyright (c) 2023. Sura
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sura\Database\Utils;

/**
 * Static class.
 */
trait StaticClass
{
    /**
     * Class is static and cannot be instantiated.
     */
    final private function __construct()
    {
    }


    /**
     * Call to undefined static method.
     * @throws MemberAccessException
     * @throws \ReflectionException
     */
    public static function __callStatic(string $name, array $args)
    {
        ObjectHelpers::strictStaticCall(static::class, $name);
    }
}