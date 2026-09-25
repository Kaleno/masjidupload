<?php

namespace App\Http\Controllers\Ketua;

use App\Http\Requests\Ketua\StoreHolidayRequest;
use App\Models\Holiday;
use App\Services\AuditLogger;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class HolidayController extends KetuaController
{
    public function index(): View
    {
        return view('ketua.holidays.index', [
            'holidays' => Holiday::query()->orderByDesc('date')->get(),
        ]);
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $from = Carbon::parse($request->string('date_from')->toString())->startOfDay();
        $to = Carbon::parse($request->string('date_to')->toString())->startOfDay();
        $name = $request->string('name')->toString();

        $created = 0;
        $skipped = 0;

        foreach (CarbonPeriod::create($from, $to) as $day) {
            $date = $day->toDateString();

            if (Holiday::query()->whereDate('date', $date)->exists()) {
                $skipped++;

                continue;
            }

            Holiday::query()->create([
                'date' => $date,
                'name' => $name,
                'organization_id' => $request->user()->organization_id,
            ]);
            $created++;
        }

        if ($created > 0) {
            app(AuditLogger::class)->record(
                $request->user(),
                'menambahkan libur '.$name.' ('.$created.' tanggal, '.$from->format('d/m/Y').'–'.$to->format('d/m/Y').')',
            );
        }

        if ($created === 0) {
            return back()->with('status', 'Semua tanggal di rentang itu sudah terdaftar sebagai libur.');
        }

        $message = $created === 1
            ? '1 tanggal libur ditambahkan.'
            : "{$created} tanggal libur ditambahkan.";

        if ($skipped > 0) {
            $message .= " {$skipped} tanggal dilewati karena sudah ada.";
        }

        return back()->with('status', $message);
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        app(AuditLogger::class)->record(
            request()->user(),
            'menghapus libur '.$holiday->name.' tanggal '.$holiday->date->format('d/m/Y'),
        );
        $holiday->delete();

        return back()->with('status', 'Tanggal libur dihapus.');
    }
}
