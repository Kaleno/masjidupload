<?php

namespace App\Support;

use App\Models\Organization;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

final class AppSettings
{
    public const SppMonthlyAmount = 'spp_monthly_amount';

    public const SppDueDay = 'spp_due_day';

    public const DefaultSppMonthlyAmount = 25000;

    public const DefaultSppDueDay = 10;

    public static function sppMonthlyAmount(): int
    {
        return (int) self::get(self::SppMonthlyAmount, (string) self::DefaultSppMonthlyAmount);
    }

    public static function setSppMonthlyAmount(int $amount): void
    {
        self::set(self::SppMonthlyAmount, (string) $amount);
    }

    public static function sppDueDay(): int
    {
        $day = (int) self::get(self::SppDueDay, (string) self::DefaultSppDueDay);

        return max(1, min(28, $day));
    }

    public static function setSppDueDay(int $day): void
    {
        self::set(self::SppDueDay, (string) max(1, min(28, $day)));
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $organizationId = self::organizationId();

        return Cache::rememberForever(self::cacheKey($key, $organizationId), function () use ($key, $default, $organizationId) {
            $row = Setting::query()
                ->where('key', $key)
                ->where('organization_id', $organizationId)
                ->first();

            return $row?->value ?? $default;
        });
    }

    public static function set(string $key, string $value): void
    {
        $organizationId = self::organizationId();

        Setting::query()->updateOrCreate(
            [
                'key' => $key,
                'organization_id' => $organizationId,
            ],
            ['value' => $value],
        );

        Cache::forget(self::cacheKey($key, $organizationId));
    }

    private static function organizationId(): ?int
    {
        if (OrganizationContext::isBound()) {
            return OrganizationContext::id();
        }

        $ids = Organization::query()->orderBy('id')->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private static function cacheKey(string $key, ?int $organizationId): string
    {
        return 'app_setting:'.($organizationId ?? 'global').':'.$key;
    }
}
