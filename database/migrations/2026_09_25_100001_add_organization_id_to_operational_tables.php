<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production already has this schema from the original (uncommitted) migrations,
 * so every step is guarded and becomes a no-op there.
 */
return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $tables = [
        'settings',
        'santri_registrations',
        'santri_profiles',
        'holidays',
        'hafalan_setoran',
        'finance_entries',
        'attendance_sessions',
        'spp_payments',
    ];

    /**
     * Columns that were unique app-wide and become unique per organization.
     *
     * @var array<string, string>
     */
    private array $perOrganizationUnique = [
        'settings' => 'key',
        'holidays' => 'date',
        'attendance_sessions' => 'session_date',
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasColumn($name, 'organization_id')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        foreach ($this->perOrganizationUnique as $name => $column) {
            if (Schema::hasIndex($name, ['organization_id', $column], 'unique')) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) use ($name, $column) {
                if (Schema::hasIndex($name, [$column], 'unique')) {
                    $table->dropUnique([$column]);
                }

                $table->unique(['organization_id', $column]);
            });
        }
    }

    public function down(): void
    {
        foreach ($this->perOrganizationUnique as $name => $column) {
            Schema::table($name, function (Blueprint $table) use ($column) {
                $table->dropUnique(['organization_id', $column]);
                $table->unique($column);
            });
        }

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('organization_id');
            });
        }
    }
};
