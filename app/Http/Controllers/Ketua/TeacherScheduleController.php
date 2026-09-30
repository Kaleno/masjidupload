<?php

namespace App\Http\Controllers\Ketua;

use App\Http\Requests\Ketua\StoreTeacherScheduleRequest;
use App\Models\TeacherSchedule;
use App\Services\AuditLogger;
use App\Services\OperationalCalendar;
use App\Services\TeacherRoster;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TeacherScheduleController extends KetuaController
{
    public function __construct(
        private TeacherRoster $roster,
        private OperationalCalendar $calendar,
        private AuditLogger $audit,
    ) {}

    public function index(): View
    {
        $today = now()->toDateString();

        return view('ketua.teacher-schedules.index', [
            'teachers' => $this->roster->teachers(),
            'upcoming' => TeacherSchedule::query()
                ->with('teachers')
                ->whereDate('date', '>=', $today)
                ->orderBy('date')
                ->get(),
            'past' => TeacherSchedule::query()
                ->with('teachers')
                ->whereDate('date', '<', $today)
                ->orderByDesc('date')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(StoreTeacherScheduleRequest $request): RedirectResponse
    {
        $from = Carbon::parse($request->string('date_from')->toString())->startOfDay();
        $to = Carbon::parse($request->string('date_to')->toString())->startOfDay();
        $mode = $request->string('mode')->toString();
        $teacherIds = $mode === TeacherSchedule::ModeSelected
            ? $this->roster->teachers()->pluck('id')->intersect(array_map('intval', $request->input('teacher_ids', [])))->values()->all()
            : [];

        if ($mode === TeacherSchedule::ModeSelected && $teacherIds === []) {
            return back()->withInput()->withErrors(['teacher_ids' => 'Centang minimal satu pengajar yang hadir.']);
        }

        $saved = 0;
        $skipped = 0;

        DB::transaction(function () use ($request, $from, $to, $mode, $teacherIds, &$saved, &$skipped): void {
            foreach (CarbonPeriod::create($from, $to) as $day) {
                if ($this->calendar->isOffDay($day)) {
                    $skipped++;

                    continue;
                }

                $schedule = TeacherSchedule::query()->whereDate('date', $day->toDateString())->first()
                    ?? new TeacherSchedule(['date' => $day->toDateString()]);

                $schedule->fill([
                    'mode' => $mode,
                    'note' => $request->input('note'),
                    'created_by' => $request->user()->id,
                    'organization_id' => $request->user()->organization_id,
                ])->save();

                $schedule->teachers()->sync($teacherIds);
                $saved++;
            }
        });

        if ($saved === 0) {
            return back()->withInput()->with('status', 'Semua tanggal di rentang itu hari libur, jadi tidak ada jadwal yang disimpan.');
        }

        $who = $mode === TeacherSchedule::ModeSelected
            ? $this->roster->teachers()->whereIn('id', $teacherIds)->pluck('name')->implode(', ')
            : 'semua pengajar';

        $this->audit->record(
            $request->user(),
            'mengatur jadwal pengajar ('.$who.') untuk '.$saved.' tanggal, '.$from->format('d/m/Y').'–'.$to->format('d/m/Y'),
        );

        $message = $saved === 1 ? 'Jadwal 1 tanggal disimpan.' : "Jadwal {$saved} tanggal disimpan.";
        if ($skipped > 0) {
            $message .= " {$skipped} tanggal dilewati karena libur/akhir pekan.";
        }

        return redirect()->route('ketua.teacher-schedules.index')->with('status', $message);
    }

    public function destroy(TeacherSchedule $teacherSchedule): RedirectResponse
    {
        abort_unless(request()->user()?->can('manage-holidays'), 403);

        $this->audit->record(
            request()->user(),
            'menghapus jadwal pengajar tanggal '.$teacherSchedule->date->format('d/m/Y').' (kembali ke semua pengajar)',
        );
        $teacherSchedule->delete();

        return back()->with('status', 'Jadwal dihapus. Tanggal itu kembali ke semua pengajar masuk.');
    }
}
