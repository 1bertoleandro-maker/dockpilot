<?php

declare(strict_types=1);

namespace App\Support;

final class CurrentCompany
{
    private static ?int $id = null;

    /**
     * Nested tenant contexts (sync jobs must not wipe the parent company).
     *
     * @var list<?int>
     */
    private static array $stack = [];

    /**
     * Only the explicit tenant context set by middleware/jobs.
     * Never fall back to auth()->user() — that recurses through User's company global scope.
     */
    public static function id(): ?int
    {
        return self::$id;
    }

    public static function set(?int $id): void
    {
        self::$stack[] = self::$id;
        self::$id = $id;
    }

    public static function clear(): void
    {
        self::$id = self::$stack === []
            ? null
            : array_pop(self::$stack);
    }

    public static function check(): int
    {
        $id = self::id();

        if ($id === null) {
            throw new \RuntimeException('Current company context is not set.');
        }

        return $id;
    }
}
