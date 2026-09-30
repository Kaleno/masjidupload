<?php

namespace App\Http\Controllers\Ops;

use App\Enums\AbsenceRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Services\AbsenceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbsenceRequestController extends Controller
{
    public function __construct(private AbsenceRequestService $service) {}

    public function index(): View
    {
        return view('ops.absence-requests.index', [
            'pending' => AbsenceRequest::query()
                ->with('santri.user')
                ->where('status', AbsenceRequestStatus::Pending)
                ->orderBy('date_from')
                ->orderBy('id')
                ->get(),
            'history' => AbsenceRequest::query()
                ->with(['santri.user', 'reviewer'])
                ->where('status', '!=', AbsenceRequestStatus::Pending)
                ->latest('updated_at')
                ->limit(30)
                ->get(),
        ]);
    }

    public function approve(Request $request, AbsenceRequest $absenceRequest): RedirectResponse
    {
        $this->service->approve($absenceRequest, $request->user(), $this->note($request));

        return back()->with('status', 'Pengajuan disetujui. Absensi tanggal tersebut otomatis diisi '.$absenceRequest->type->label().'.');
    }

    public function reject(Request $request, AbsenceRequest $absenceRequest): RedirectResponse
    {
        $this->service->reject($absenceRequest, $request->user(), $this->note($request));

        return back()->with('status', 'Pengajuan ditolak. Absensi tanggal tersebut otomatis diisi Alfa.');
    }

    private function note(Request $request): ?string
    {
        $request->validate(['review_note' => ['nullable', 'string', 'max:255']]);

        return $request->filled('review_note') ? $request->string('review_note')->toString() : null;
    }
}
