<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Services\SppReport;
use App\Support\MonthRange;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SppReportController extends Controller
{
    public function __construct(private SppReport $report) {}

    public function index(Request $request): View
    {
        $range = MonthRange::fromRequest($request);

        return view('laporan.spp.index', [
            'range' => $range,
            'report' => $this->report->build($range),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $range = MonthRange::fromRequest($request);
        $placeName = $request->user()->organization?->name ?? config('app.name');

        return $this->report
            ->toXlsx($range, $placeName)
            ->download('laporan-spp-'.$range->slug().'.xlsx');
    }
}
