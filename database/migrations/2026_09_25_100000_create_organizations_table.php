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
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('ketua_user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasColumn('users', 'organization_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('organization_id')->nullable()->after('id')->constrained()->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('users', 'session_date')) {
            Schema::table('users', function (Blueprint $table) {
                $table->date('session_date')->nullable()->after('remember_token');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn('session_date');
        });

        Schema::dropIfExists('organizations');
    }
};
