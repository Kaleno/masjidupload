<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teacher_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('mode', 20)->default('all');
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'date']);
        });

        Schema::create('teacher_schedule_user', function (Blueprint $table) {
            $table->foreignId('teacher_schedule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->primary(['teacher_schedule_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_schedule_user');
        Schema::dropIfExists('teacher_schedules');
    }
};
