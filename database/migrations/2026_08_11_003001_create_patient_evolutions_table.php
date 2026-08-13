<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_evolutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->date('evolved_at');
            $table->text('daily_complaint')->nullable();
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->text('home_guidance_adherence')->nullable();
            $table->text('therapeutic_conduct')->nullable();
            $table->text('session_final_impression')->nullable();
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'evolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_evolutions');
    }
};
