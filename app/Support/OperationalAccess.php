<?php

namespace App\Support;

use App\Models\SantriProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class OperationalAccess
{
    public function canOperateDaily(User $user): bool
    {
        return $user->hasRole(Role::Ketua) || $user->hasAnyRole(Role::teaching());
    }

    public function canViewSantri(User $user, SantriProfile $santri): bool
    {
        if (! $this->canOperateDaily($user)) {
            return false;
        }

        if ($user->organization_id === null || $santri->organization_id === null) {
            return false;
        }

        return (int) $user->organization_id === (int) $santri->organization_id;
    }

    /**
     * @return Builder<SantriProfile>
     */
    public function activeSantriQuery(?User $user = null): Builder
    {
        return SantriProfile::query()->aktif()->with('user');
    }

    /**
     * @return list<int>
     */
    public function activeSantriIds(?User $user = null): array
    {
        return $this->activeSantriQuery($user)->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function assertCanOperate(User $user): void
    {
        abort_unless($this->canOperateDaily($user), 403);
    }

    public function assertSantri(User $user, SantriProfile $santri): void
    {
        abort_unless($this->canViewSantri($user, $santri), 403);
    }
}
