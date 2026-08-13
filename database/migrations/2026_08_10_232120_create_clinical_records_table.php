<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clinical_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->string('type');
            $table->date('performed_at');
            $table->unsignedTinyInteger('pain_level')->nullable();
            $table->text('complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('functional_limitations')->nullable();
            $table->text('treatment_objective')->nullable();
            $table->text('physical_assessment')->nullable();
            $table->text('conduct')->nullable();
            $table->text('next_steps')->nullable();
            $table->text('observations')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'performed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clinical_records');
    }
};
