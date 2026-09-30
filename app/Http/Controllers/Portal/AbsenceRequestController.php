<?php

namespace App\Http\Controllers\Portal;

use App\Enums\AttendanceStatus;
use App\Enums\SantriStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreAbsenceRequestRequest;
use App\Models\AbsenceRequest;
use App\Models\SantriProfile;
use App\Services\AbsenceRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AbsenceRequestController extends Controller
{
    public function __construct(private AbsenceRequestService $service) {}

    public function index(Request $request): View
    {
        $santri = $this->santri($request);

        return view('portal.absence-requests.index', [
            'santri' => $santri,
            'requests' => AbsenceRequest::query()
                ->with('reviewer')
                ->where('santri_id', $santri->id)
                ->latest()
                ->latest('id')
                ->limit(50)
                ->get(),
            'types' => [AttendanceStatus::Izin, AttendanceStatus::Sakit],
            'canSubmit' => $santri->status === SantriStatus::Aktif,
            'maxDays' => StoreAbsenceRequestRequest::MaxDays,
        ]);
    }

    public function store(StoreAbsenceRequestRequest $request): RedirectResponse
    {
        $santri = $this->santri($request);
        abort_unless($santri->status === SantriStatus::Aktif, 403, 'Hanya santri aktif yang bisa mengajukan izin.');

        $this->service->submit($santri, $request->validated());

        return redirect()
            ->route('portal.absence-requests.index')
            ->with('status', 'Pengajuan terkirim. Statusnya "Menunggu" sampai Ketua DKM / Ketua Pengajar merespons.');
    }

    public function cancel(Request $request, AbsenceRequest $absenceRequest): RedirectResponse
    {
        $santri = $this->santri($request);
        abort_unless((int) $absenceRequest->santri_id === (int) $santri->id, 404);

        $this->service->cancel($absenceRequest);

        return back()->with('status', 'Pengajuan dibatalkan.');
    }

    private function santri(Request $request): SantriProfile
    {
        $santri = $request->user()->santriProfile;
        abort_unless($santri, 404);

        return $santri;
    }
}
