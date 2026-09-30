<?php

namespace App\Services;

use App\Enums\AbsenceRequestStatus;
use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\SantriProfile;
use App\Models\User;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sessions opened later pick up reviewed requests in AttendanceSessionService::syncMembers().
 */
class AbsenceRequestService
{
    public function __construct(
        private OperationalCalendar $calendar,
        private AuditLogger $audit,
    ) {}

    /**
     * @param  array{date_from: string, date_to: string, type: string, reason: string}  $data
     */
    public function submit(SantriProfile $santri, array $data): AbsenceRequest
    {
        $from = Carbon::parse($data['date_from'])->startOfDay();
        $to = Carbon::parse($data['date_to'])->startOfDay();

        if ($this->schoolDays($from, $to) === []) {
            throw ValidationException::withMessages([
                'date_from' => 'Rentang tanggal itu semuanya hari libur, jadi tidak perlu pengajuan.',
            ]);
        }

        $overlap = AbsenceRequest::query()
            ->where('santri_id', $santri->id)
            ->whereIn('status', [AbsenceRequestStatus::Pending, AbsenceRequestStatus::Approved])
            ->whereDate('date_from', '<=', $to->toDateString())
            ->whereDate('date_to', '>=', $from->toDateString())
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'date_from' => 'Sudah ada pengajuan yang menunggu atau disetujui di tanggal tersebut.',
            ]);
        }

        return AbsenceRequest::query()->create([
            'santri_id' => $santri->id,
            'date_from' => $from->toDateString(),
            'date_to' => $to->toDateString(),
            'type' => $data['type'],
            'reason' => $data['reason'],
            'status' => AbsenceRequestStatus::Pending,
            'organization_id' => $santri->organization_id,
        ]);
    }

    public function approve(AbsenceRequest $request, User $reviewer, ?string $note = null): void
    {
        $this->review($request, $reviewer, AbsenceRequestStatus::Approved, $note);
    }

    public function reject(AbsenceRequest $request, User $reviewer, ?string $note = null): void
    {
        $this->review($request, $reviewer, AbsenceRequestStatus::Rejected, $note);
    }

    public function cancel(AbsenceRequest $request): void
    {
        abort_unless($request->isPending(), 422, 'Pengajuan ini sudah diproses.');

        $request->update(['status' => AbsenceRequestStatus::Cancelled]);
    }

    /**
     * Reviewed requests covering the date, keyed by santri id, used when a session's rows are created.
     *
     * @return Collection<int, AbsenceRequest>
     */
    public function resolvedOn(string $date): Collection
    {
        return AbsenceRequest::query()
            ->whereIn('status', [AbsenceRequestStatus::Approved, AbsenceRequestStatus::Rejected])
            ->covering($date)
            ->orderBy('reviewed_at')
            ->get()
            ->keyBy('santri_id');
    }

    /**
     * @return list<string>
     */
    public function schoolDays(Carbon $from, Carbon $to): array
    {
        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if (! $this->calendar->isOffDay($day)) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }

    private function review(AbsenceRequest $request, User $reviewer, AbsenceRequestStatus $status, ?string $note): void
    {
        abort_unless($request->isPending(), 422, 'Pengajuan ini sudah diproses.');

        DB::transaction(function () use ($request, $reviewer, $status, $note): void {
            $request->update([
                'status' => $status,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            $this->applyToExistingSessions($request);

            $request->loadMissing('santri.user');
            $verb = $status === AbsenceRequestStatus::Approved ? 'menyetujui' : 'menolak';

            $this->audit->record(
                $reviewer,
                $verb.' pengajuan '.mb_strtolower($request->type->label()).' santri '
                .($request->santri->user->name ?? 'santri').' untuk '.$request->periodLabel(),
            );
        });
    }

    private function applyToExistingSessions(AbsenceRequest $request): void
    {
        $status = $request->resultingStatus();
        if ($status === null) {
            return;
        }

        $dates = $this->schoolDays($request->date_from->copy(), $request->date_to->copy());
        if ($dates === []) {
            return;
        }

        $sessions = AttendanceSession::query()
            ->where('organization_id', $request->organization_id)
            ->where(function ($query) use ($dates): void {
                foreach ($dates as $date) {
                    $query->orWhereDate('session_date', $date);
                }
            })
            ->get();

        foreach ($sessions as $session) {
            Attendance::query()->updateOrCreate(
                [
                    'attendance_session_id' => $session->id,
                    'santri_id' => $request->santri_id,
                ],
                [
                    'status' => $status,
                    'note' => $request->attendanceNote(),
                ],
            );
        }
    }
}
