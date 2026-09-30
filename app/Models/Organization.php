<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'ketua_user_id'])]
class Organization extends Model
{
    /**
     * Creates (or reuses) the place owned by a Ketua and links the Ketua to it.
     */
    public static function provision(User $ketua): self
    {
        $organization = $ketua->organization_id
            ? static::query()->find($ketua->organization_id)
            : null;

        $organization ??= static::query()->firstOrCreate(
            ['ketua_user_id' => $ketua->id],
            ['name' => $ketua->place_name ?: $ketua->name],
        );

        if ($organization->ketua_user_id === null) {
            $organization->update(['ketua_user_id' => $ketua->id]);
        }

        if ((int) $ketua->organization_id !== $organization->id) {
            $ketua->forceFill(['organization_id' => $organization->id])->save();
        }

        return $organization;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ketua(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ketua_user_id');
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
