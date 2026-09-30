<?php

namespace App\Models;

use App\Enums\AbsenceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Support\DateLabel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'santri_id',
    'date_from',
    'date_to',
    'type',
    'reason',
    'status',
    'reviewed_by',
    'reviewed_at',
    'review_note',
    'organization_id',
])]
class AbsenceRequest extends Model
{
    use BelongsToOrganization;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'type' => AttendanceStatus::class,
            'status' => AbsenceRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<SantriProfile, $this>
     */
    public function santri(): BelongsTo
    {
        return $this->belongsTo(SantriProfile::class, 'santri_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by')->withTrashed();
    }

    /**
     * @param  Builder<AbsenceRequest>  $query
     * @return Builder<AbsenceRequest>
     */
    public function scopeCovering(Builder $query, string $date): Builder
    {
        return $query->whereDate('date_from', '<=', $date)->whereDate('date_to', '>=', $date);
    }

    public function isPending(): bool
    {
        return $this->status === AbsenceRequestStatus::Pending;
    }

    public function periodLabel(): string
    {
        if ($this->date_from->isSameDay($this->date_to)) {
            return DateLabel::long($this->date_from);
        }

        return DateLabel::dayMonthYear($this->date_from).' – '.DateLabel::dayMonthYear($this->date_to);
    }

    /**
     * Attendance status the request resolves to once reviewed.
     */
    public function resultingStatus(): ?AttendanceStatus
    {
        return match ($this->status) {
            AbsenceRequestStatus::Approved => $this->type,
            AbsenceRequestStatus::Rejected => AttendanceStatus::Alfa,
            default => null,
        };
    }

    public function attendanceNote(): string
    {
        $prefix = $this->status === AbsenceRequestStatus::Rejected
            ? 'Pengajuan '.mb_strtolower($this->type->label()).' ditolak'
            : 'Pengajuan '.mb_strtolower($this->type->label());

        return mb_substr($prefix.': '.$this->reason, 0, 255);
    }
}
