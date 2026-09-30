<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absence_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('santri_id')->constrained('santri_profiles')->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->string('type', 20);
            $table->text('reason');
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'status']);
            $table->index(['santri_id', 'date_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absence_requests');
    }
};
