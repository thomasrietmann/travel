<?php

namespace App\Support;

/** Technical compatibility only; hosting security patches remain a separate requirement. */
final class PhpRuntime
{
    public const MINIMUM_VERSION = '8.3.21';

    public static function supports(string $version = PHP_VERSION): bool
    {
        return version_compare($version, self::MINIMUM_VERSION, '>=')
            && version_compare($version, '9.0.0', '<');
    }

    public static function requirement(): string
    {
        return 'PHP >= '.self::MINIMUM_VERSION.' und < 9.0';
    }
}
