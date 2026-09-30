<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Role;

class AuditLogger
{
    /**
     * Stores "<Peran> <Nama> <aksi>", e.g. "Ketua Pengajar Budi menginput pembayaran SPP ...".
     */
    public function record(User $actor, string $action): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor->id,
            'remark' => trim($this->actorLabel($actor).' '.$action),
            'organization_id' => $actor->organization_id,
        ]);
    }

    public function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function actorLabel(User $actor): string
    {
        $roles = $actor->getRoleNames();
        $role = collect(Role::all())->first(fn (string $role): bool => $roles->contains($role));

        return trim(($role ? Role::label($role) : '').' '.$actor->name);
    }
}
