<?php

namespace App\Http\Controllers\Laporan;

use App\Http\Controllers\Controller;
use App\Services\AttendanceReport;
use App\Support\MonthRange;
use App\Support\OperationalAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AttendanceReportController extends Controller
{
    public function __construct(
        private AttendanceReport $report,
        private OperationalAccess $access,
    ) {}

    public function index(Request $request): View
    {
        $this->access->assertCanOperate($request->user());
        $range = MonthRange::fromRequest($request);

        return view('laporan.absensi.index', [
            'range' => $range,
            'months' => $this->report->build($range),
        ]);
    }

    public function download(Request $request): BinaryFileResponse
    {
        $this->access->assertCanOperate($request->user());
        $range = MonthRange::fromRequest($request);
        $placeName = $request->user()->organization?->name ?? config('app.name');

        return $this->report
            ->toXlsx($range, $placeName)
            ->download('laporan-absensi-'.$range->slug().'.xlsx');
    }
}
