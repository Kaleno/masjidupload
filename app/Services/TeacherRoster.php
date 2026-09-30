<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\TeacherSchedule;
use App\Models\User;
use App\Support\Role;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who teaches on a given day: every active teacher unless a TeacherSchedule narrows it down.
 */
class TeacherRoster
{
    /**
     * @return Collection<int, User>
     */
    public function teachers(): Collection
    {
        return User::query()
            ->role(Role::teaching())
            ->where('is_active', true)
            ->with('roles')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function forDate(CarbonInterface $date): Collection
    {
        $schedule = TeacherSchedule::query()
            ->with('teachers')
            ->whereDate('date', $date->toDateString())
            ->first();

        return $this->resolve($schedule, $this->teachers());
    }

    /**
     * @return array<string, array{date: Carbon, weekend: bool, holiday: ?string, schedule: ?TeacherSchedule, teachers: Collection<int, User>}>
     */
    public function month(CarbonInterface $month): array
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $schedules = TeacherSchedule::query()
            ->with('teachers')
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (TeacherSchedule $schedule): string => $schedule->date->toDateString());

        $teachers = $this->teachers();
        $days = [];

        for ($day = $start->copy(); $day->lessThanOrEqualTo($end); $day->addDay()) {
            $key = $day->toDateString();
            $weekend = $day->isoWeekday() >= 6;
            $holiday = $holidays->get($key)?->name;
            $schedule = $schedules->get($key);
            $off = $weekend || $holiday !== null;

            $days[$key] = [
                'date' => $day->copy(),
                'weekend' => $weekend,
                'holiday' => $holiday,
                'schedule' => $off ? null : $schedule,
                'teachers' => $off ? collect() : $this->resolve($schedule, $teachers),
            ];
        }

        return $days;
    }

    /**
     * @param  Collection<int, User>  $allTeachers
     * @return Collection<int, User>
     */
    private function resolve(?TeacherSchedule $schedule, Collection $allTeachers): Collection
    {
        if ($schedule === null || $schedule->isAllTeachers()) {
            return $allTeachers;
        }

        $ids = $schedule->teachers->pluck('id');

        return $allTeachers->whereIn('id', $ids)->values();
    }
}
