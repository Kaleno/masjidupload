<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AbsenceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Models\User;
use App\Services\TeacherRoster;
use App\Support\DateLabel;
use App\Support\Role;
use App\Support\WhatsApp;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private TeacherRoster $roster) {}

    public function __invoke(Request $request): View
    {
        $santri = $request->user()->santriProfile;
        abort_unless($santri, 404);

        $month = $this->month($request->input('bulan'));
        $days = $this->roster->month($month);

        $requests = AbsenceRequest::query()
            ->where('santri_id', $santri->id)
            ->whereIn('status', [AbsenceRequestStatus::Pending, AbsenceRequestStatus::Approved])
            ->whereDate('date_from', '<=', $month->copy()->endOfMonth()->toDateString())
            ->whereDate('date_to', '>=', $month->toDateString())
            ->get();

        $greeting = "Assalamu'alaikum, saya {$santri->user->name} (santri ".config('app.name').').';

        $calendar = collect($days)->map(function (array $day) use ($requests, $greeting): array {
            $date = $day['date'];
            $request = $requests->first(fn (AbsenceRequest $r): bool => $date->between($r->date_from, $r->date_to));

            return [
                'date' => $date->toDateString(),
                'day' => (int) $date->day,
                'label' => DateLabel::long($date),
                'isToday' => $date->isToday(),
                'off' => $day['weekend'] || $day['holiday'] !== null,
                'holiday' => $day['holiday'] ?? ($day['weekend'] ? 'Akhir pekan' : null),
                'partial' => $day['schedule'] !== null && ! $day['schedule']->isAllTeachers(),
                'note' => $day['schedule']?->note,
                'request' => $request ? [
                    'type' => $request->type->label(),
                    'status' => $request->status->label(),
                    'tone' => $request->status->badgeTone(),
                ] : null,
                'teachers' => $day['teachers']->map(fn (User $teacher): array => [
                    'name' => $teacher->name,
                    'role' => Role::label($teacher->getRoleNames()->first() ?? ''),
                    'phone' => WhatsApp::display($teacher->phone),
                    'wa' => WhatsApp::link($teacher->phone, $greeting),
                ])->values()->all(),
            ];
        })->values();

        return view('portal.schedule', [
            'month' => $month,
            'monthLabel' => $month->copy()->locale(app()->getLocale())->translatedFormat('F Y'),
            'leadingBlanks' => $month->isoWeekday() - 1,
            'calendar' => $calendar,
            'prev' => $month->copy()->subMonthNoOverflow()->format('Y-m'),
            'next' => $month->copy()->addMonthNoOverflow()->format('Y-m'),
            'isCurrentMonth' => $month->isSameMonth(now()),
        ]);
    }

    private function month(mixed $value): Carbon
    {
        if (is_string($value) && preg_match('/^(\d{4})-(\d{2})$/', $value, $m) && (int) $m[2] >= 1 && (int) $m[2] <= 12) {
            return Carbon::create((int) $m[1], (int) $m[2], 1)->startOfMonth();
        }

        return now()->startOfMonth();
    }
}
