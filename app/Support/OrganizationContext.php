<?php

namespace App\Support;

final class OrganizationContext
{
    private static ?int $id = null;

    public static function set(int $id): void
    {
        self::$id = $id;
    }

    public static function forget(): void
    {
        self::$id = null;
    }

    public static function isBound(): bool
    {
        return self::$id !== null;
    }

    public static function id(): ?int
    {
        return self::$id;
    }
}
