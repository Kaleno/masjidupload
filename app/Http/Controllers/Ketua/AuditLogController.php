<?php

namespace App\Http\Controllers\Ketua;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends KetuaController
{
    public function index(Request $request): View
    {
        $logs = AuditLog::query()
            ->where('organization_id', $request->user()->organization_id)
            ->latest()
            ->latest('id')
            ->limit(300)
            ->get();

        return view('ketua.audit.index', [
            'logs' => $logs,
        ]);
    }
}
