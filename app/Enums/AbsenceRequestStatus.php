<?php

namespace App\Enums;

enum AbsenceRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function badgeTone(): string
    {
        return match ($this) {
            self::Pending => 'warn',
            self::Approved => 'ok',
            self::Rejected => 'danger',
            self::Cancelled => 'muted',
        };
    }
}
