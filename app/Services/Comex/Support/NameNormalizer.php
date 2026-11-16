<?php

declare(strict_types=1);

namespace App\Services\Comex\Support;

final class NameNormalizer
{
    public static function normalize(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $value = mb_strtoupper(trim($name), 'UTF-8');
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', '', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';

        return trim($value);
    }

    public static function equals(?string $a, ?string $b): bool
    {
        $left = self::normalize($a);
        $right = self::normalize($b);

        if ($left === '' || $right === '') {
            return true;
        }

        return $left === $right;
    }
}
