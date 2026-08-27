<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\Recommendations\Support;

/** Raw aggregate rows come back untyped; narrowing them is a boundary, not a cast. */
final class Cast
{
    public static function int(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    public static function str(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
