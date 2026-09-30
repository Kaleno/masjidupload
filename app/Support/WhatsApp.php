<?php

namespace App\Support;

final class WhatsApp
{
    /**
     * Accepts 08xx, 628xx, +62 8xx, 8xx or a pasted https://wa.me/62xx link and returns 628xx.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '62'.$digits;
        }

        return $digits !== '' ? $digits : null;
    }

    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);

        return $normalized !== null && preg_match('/^628\d{7,12}$/', $normalized) === 1;
    }

    public static function link(?string $phone, ?string $message = null): ?string
    {
        if (! self::isValid($phone)) {
            return null;
        }

        $url = 'https://wa.me/'.self::normalize($phone);

        return $message ? $url.'?text='.rawurlencode($message) : $url;
    }

    /**
     * 6281234567890 → 0812-3456-7890 for display.
     */
    public static function display(?string $phone): ?string
    {
        $normalized = self::normalize($phone);

        if ($normalized === null || ! str_starts_with($normalized, '62')) {
            return $phone;
        }

        $local = '0'.substr($normalized, 2);

        return trim(implode('-', str_split($local, 4)), '-');
    }
}
