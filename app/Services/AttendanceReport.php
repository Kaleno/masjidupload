<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Holiday;
use App\Models\SantriProfile;
use App\Support\MonthRange;
use App\Support\WeekDay;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AttendanceReport
{
    public const Holiday = 'libur';

    /**
     * @return list<array{
     *     month: Carbon,
     *     label: string,
     *     days: list<array{date: string, day: int, weekday: string, holiday: ?string}>,
     *     rows: list<array{santri: SantriProfile, cells: list<?string>, totals: array<string, int>}>
     * }>
     */
    public function build(MonthRange $range): array
    {
        $start = $range->from->copy()->startOfMonth();
        $end = $range->to->copy()->endOfMonth();

        $holidays = Holiday::query()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (Holiday $holiday): string => $holiday->date->toDateString());

        $attendances = Attendance::query()
            ->with('session:id,session_date')
            ->whereHas('session', function ($query) use ($start, $end): void {
                $query->whereDate('session_date', '>=', $start->toDateString())
                    ->whereDate('session_date', '<=', $end->toDateString())
                    ->whereNotNull('submitted_at');
            })
            ->get(['id', 'attendance_session_id', 'santri_id', 'status']);

        /** @var array<int, array<string, string>> $statusByDate */
        $statusByDate = [];
        foreach ($attendances as $attendance) {
            $date = $attendance->session?->session_date?->toDateString();
            if ($date !== null) {
                $statusByDate[$attendance->santri_id][$date] = $attendance->status instanceof AttendanceStatus
                    ? $attendance->status->value
                    : (string) $attendance->status;
            }
        }

        $santris = $this->santris(array_keys($statusByDate));

        return array_map(
            fn (Carbon $month): array => $this->month($month, $holidays, $santris, $statusByDate),
            $range->months(),
        );
    }

    public function toXlsx(MonthRange $range, string $placeName): XlsxWriter
    {
        $writer = new XlsxWriter;
        $statusColumns = AttendanceStatus::cases();

        foreach ($this->build($range) as $month) {
            $dayCount = count($month['days']);

            $rows = [
                [['value' => 'Laporan Absensi – '.$placeName, 'style' => XlsxWriter::Title]],
                [['value' => 'Bulan: '.$month['label'], 'style' => XlsxWriter::Muted]],
                [['value' => 'Dicetak: '.now()->format('d/m/Y H:i'), 'style' => XlsxWriter::Muted]],
                [],
                [
                    ['value' => 'Nama Santri', 'style' => XlsxWriter::Header],
                    ...array_map(fn (array $day): array => ['value' => $day['day'], 'style' => XlsxWriter::Header], $month['days']),
                    ...array_map(fn (AttendanceStatus $status): array => ['value' => $status->label(), 'style' => XlsxWriter::Header], $statusColumns),
                ],
                [
                    ['value' => '', 'style' => XlsxWriter::Header],
                    ...array_map(fn (array $day): array => ['value' => $day['weekday'], 'style' => XlsxWriter::Header], $month['days']),
                    ...array_map(fn (): array => ['value' => '', 'style' => XlsxWriter::Header], $statusColumns),
                ],
            ];

            foreach ($month['rows'] as $row) {
                $cells = [];
                foreach ($row['cells'] as $value) {
                    $cells[] = match (true) {
                        $value === self::Holiday => ['value' => 'Libur', 'style' => XlsxWriter::Holiday],
                        $value === AttendanceStatus::Alfa->value => ['value' => 'Alfa', 'style' => XlsxWriter::Warning],
                        $value === null => '',
                        default => AttendanceStatus::tryFrom($value)?->label() ?? $value,
                    };
                }

                $rows[] = [
                    $row['santri']->user->name ?? '-',
                    ...$cells,
                    ...array_map(fn (AttendanceStatus $status): int => $row['totals'][$status->value], $statusColumns),
                ];
            }

            $rows[] = [];
            $rows[] = [['value' => 'Keterangan: Libur = hari libur yang dijadwalkan. Sel kosong = belum ada absensi tersimpan. Sabtu & Minggu tidak ditampilkan.', 'style' => XlsxWriter::Muted]];

            $widths = [0 => 30];
            for ($i = 1; $i <= $dayCount; $i++) {
                $widths[$i] = 7;
            }
            foreach (array_keys($statusColumns) as $offset) {
                $widths[$dayCount + 1 + $offset] = 7;
            }

            $writer->addSheet(MonthRange::monthLabel($month['month'], short: true), $rows, [
                'widths' => $widths,
                'freeze' => 'B7',
            ]);
        }

        return $writer;
    }

    /**
     * @param  list<int>  $withAttendance
     * @return Collection<int, SantriProfile>
     */
    private function santris(array $withAttendance): Collection
    {
        return SantriProfile::query()
            ->with('user')
            ->where(function ($query) use ($withAttendance): void {
                $query->where('santri_profiles.status', 'aktif')
                    ->orWhereIn('santri_profiles.id', $withAttendance);
            })
            ->alphabetical()
            ->get();
    }

    /**
     * @param  Collection<string, Holiday>  $holidays
     * @param  Collection<int, SantriProfile>  $santris
     * @param  array<int, array<string, string>>  $statusByDate
     * @return array{month: Carbon, label: string, days: list<array{date: string, day: int, weekday: string, holiday: ?string}>, rows: list<array{santri: SantriProfile, cells: list<?string>, totals: array<string, int>}>}
     */
    private function month(Carbon $month, Collection $holidays, Collection $santris, array $statusByDate): array
    {
        $days = [];
        $cursor = $month->copy()->startOfMonth();
        $last = $month->copy()->endOfMonth();

        while ($cursor->lessThanOrEqualTo($last)) {
            if ($cursor->isoWeekday() <= 5) {
                $date = $cursor->toDateString();
                $days[] = [
                    'date' => $date,
                    'day' => (int) $cursor->day,
                    'weekday' => mb_substr(WeekDay::label($cursor->isoWeekday()), 0, 3),
                    'holiday' => $holidays->get($date)?->name,
                ];
            }
            $cursor->addDay();
        }

        $rows = $santris->map(function (SantriProfile $santri) use ($days, $statusByDate): array {
            $totals = array_fill_keys(array_map(fn (AttendanceStatus $s): string => $s->value, AttendanceStatus::cases()), 0);
            $cells = [];

            foreach ($days as $day) {
                if ($day['holiday'] !== null) {
                    $cells[] = self::Holiday;

                    continue;
                }

                $status = $statusByDate[$santri->id][$day['date']] ?? null;
                if ($status !== null && isset($totals[$status])) {
                    $totals[$status]++;
                }
                $cells[] = $status;
            }

            return ['santri' => $santri, 'cells' => $cells, 'totals' => $totals];
        })->values()->all();

        return [
            'month' => $month,
            'label' => MonthRange::monthLabel($month),
            'days' => $days,
            'rows' => $rows,
        ];
    }
}
