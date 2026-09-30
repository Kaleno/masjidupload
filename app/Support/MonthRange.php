<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

final class MonthRange
{
    public const MaxMonths = 24;

    private function __construct(
        public readonly Carbon $from,
        public readonly Carbon $to,
    ) {}

    /**
     * Reads from_month/from_year/to_month/to_year; defaults to January–current month of this year.
     */
    public static function fromRequest(Request $request): self
    {
        $now = now();

        return self::make(
            self::year($request->input('from_year'), (int) $now->year),
            self::month($request->input('from_month'), 1),
            self::year($request->input('to_year'), (int) $now->year),
            self::month($request->input('to_month'), (int) $now->month),
        );
    }

    public static function make(int $fromYear, int $fromMonth, int $toYear, int $toMonth): self
    {
        $from = Carbon::create($fromYear, $fromMonth, 1)->startOfMonth();
        $to = Carbon::create($toYear, $toMonth, 1)->startOfMonth();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if (self::monthsBetween($from, $to) >= self::MaxMonths) {
            $from = $to->copy()->subMonthsNoOverflow(self::MaxMonths - 1);
        }

        return new self($from, $to);
    }

    /**
     * @return list<Carbon>
     */
    public function months(): array
    {
        $months = [];
        $cursor = $this->from->copy();

        while ($cursor->lessThanOrEqualTo($this->to)) {
            $months[] = $cursor->copy();
            $cursor->addMonthNoOverflow();
        }

        return $months;
    }

    public function spansYears(): bool
    {
        return $this->from->year !== $this->to->year;
    }

    public function label(): string
    {
        $from = self::monthLabel($this->from);
        $to = self::monthLabel($this->to);

        return $from === $to ? $from : $from.' – '.$to;
    }

    public function slug(): string
    {
        return $this->from->format('Y-m').'_'.$this->to->format('Y-m');
    }

    /**
     * @return array{from_month: int, from_year: int, to_month: int, to_year: int}
     */
    public function query(): array
    {
        return [
            'from_month' => (int) $this->from->month,
            'from_year' => (int) $this->from->year,
            'to_month' => (int) $this->to->month,
            'to_year' => (int) $this->to->year,
        ];
    }

    public static function monthLabel(Carbon $month, bool $short = false): string
    {
        return $month->copy()
            ->locale(app()->getLocale())
            ->translatedFormat($short ? 'M Y' : 'F Y');
    }

    private static function monthsBetween(Carbon $from, Carbon $to): int
    {
        return ($to->year - $from->year) * 12 + ($to->month - $from->month);
    }

    private static function year(mixed $value, int $default): int
    {
        $year = filter_var($value, FILTER_VALIDATE_INT);

        return $year !== false && $year >= 2020 && $year <= 2100 ? $year : $default;
    }

    private static function month(mixed $value, int $default): int
    {
        $month = filter_var($value, FILTER_VALIDATE_INT);

        return $month !== false && $month >= 1 && $month <= 12 ? $month : $default;
    }
}
