<?php

namespace App\Services;

use App\Enums\SantriStatus;
use App\Models\SantriProfile;
use App\Models\SppPayment;
use App\Support\MonthRange;
use App\Support\XlsxWriter;
use Illuminate\Support\Carbon;

class SppReport
{
    public const Paid = 'paid';

    public const Unpaid = 'unpaid';

    public const NotObligated = 'na';

    public function __construct(private SppService $spp) {}

    /**
     * @return array{
     *     months: list<array{key: string, label: string}>,
     *     rows: list<array{santri: SantriProfile, cells: list<array{state: string, text: string}>, paid: int}>
     * }
     */
    public function build(MonthRange $range): array
    {
        $months = $range->months();
        $fromKey = $range->from->year * 100 + $range->from->month;
        $toKey = $range->to->year * 100 + $range->to->month;

        $payments = SppPayment::query()
            ->whereRaw('(year * 100 + month) between ? and ?', [$fromKey, $toKey])
            ->get()
            ->groupBy('santri_id')
            ->map(fn ($rows) => $rows->keyBy(fn (SppPayment $p): string => sprintf('%04d-%02d', $p->year, $p->month)));

        $santris = SantriProfile::query()
            ->with('user')
            ->where(function ($query) use ($payments): void {
                $query->where('santri_profiles.status', SantriStatus::Aktif)
                    ->orWhereIn('santri_profiles.id', $payments->keys()->all());
            })
            ->alphabetical()
            ->get();

        $rows = $santris->map(function (SantriProfile $santri) use ($months, $payments): array {
            $start = $this->spp->obligationStart($santri);
            $end = $santri->graduated_at?->copy()->startOfMonth();
            $paidMonths = $payments->get($santri->id, collect());
            $paid = 0;

            $cells = array_map(function (Carbon $month) use ($start, $end, $paidMonths, &$paid): array {
                $payment = $paidMonths->get($month->format('Y-m'));

                if ($payment !== null) {
                    $paid++;

                    return ['state' => self::Paid, 'text' => $payment->paid_at->format('d/m/Y')];
                }

                if ($month->lessThan($start) || ($end !== null && $month->greaterThan($end))) {
                    return ['state' => self::NotObligated, 'text' => '-'];
                }

                return ['state' => self::Unpaid, 'text' => 'Belum'];
            }, $months);

            return ['santri' => $santri, 'cells' => $cells, 'paid' => $paid];
        })->values()->all();

        return [
            'months' => array_map(fn (Carbon $month): array => [
                'key' => $month->format('Y-m'),
                'label' => $this->monthHeader($month, $range),
            ], $months),
            'rows' => $rows,
        ];
    }

    public function toXlsx(MonthRange $range, string $placeName): XlsxWriter
    {
        $report = $this->build($range);
        $columnCount = count($report['months']) + 1;

        $rows = [
            [['value' => 'Laporan SPP – '.$placeName, 'style' => XlsxWriter::Title]],
            [['value' => 'Periode: '.$range->label(), 'style' => XlsxWriter::Muted]],
            [['value' => 'Dicetak: '.now()->format('d/m/Y H:i'), 'style' => XlsxWriter::Muted]],
            [],
            [
                ['value' => 'Nama Santri', 'style' => XlsxWriter::Header],
                ...array_map(fn (array $month): array => ['value' => $month['label'], 'style' => XlsxWriter::Header], $report['months']),
            ],
        ];

        foreach ($report['rows'] as $row) {
            $rows[] = [
                $row['santri']->user->name ?? '-',
                ...array_map(fn (array $cell): array => [
                    'value' => $cell['text'],
                    'style' => $cell['state'] === self::Unpaid ? XlsxWriter::Warning : XlsxWriter::Cell,
                ], $row['cells']),
            ];
        }

        $rows[] = [];
        $rows[] = [['value' => 'Keterangan: tanggal = tanggal pembayaran, Belum = belum dibayar, - = belum/tidak wajib bayar.', 'style' => XlsxWriter::Muted]];

        $widths = [0 => 32];
        for ($i = 1; $i < $columnCount; $i++) {
            $widths[$i] = 13;
        }

        return (new XlsxWriter)->addSheet('SPP', $rows, [
            'widths' => $widths,
            'freeze' => 'B6',
        ]);
    }

    private function monthHeader(Carbon $month, MonthRange $range): string
    {
        $month = $month->copy()->locale(app()->getLocale());

        return $range->spansYears() ? $month->translatedFormat('M Y') : $month->translatedFormat('M');
    }
}
