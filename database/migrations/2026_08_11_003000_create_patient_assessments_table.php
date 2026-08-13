<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_assessments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('professional_id')->constrained('users')->restrictOnDelete();
            $table->date('assessed_at');
            $table->string('indication')->nullable();
            $table->string('birthplace')->nullable();
            $table->string('marital_status')->nullable();
            $table->string('gender')->nullable();
            $table->string('profession')->nullable();
            $table->string('address')->nullable();
            $table->text('chief_complaint')->nullable();
            $table->text('condition_history')->nullable();
            $table->text('life_habits')->nullable();
            $table->text('personal_family_history')->nullable();
            $table->text('previous_treatments')->nullable();
            $table->text('physical_examination')->nullable();
            $table->text('complementary_exams')->nullable();
            $table->text('physical_therapy_diagnosis')->nullable();
            $table->text('cbdf')->nullable();
            $table->unsignedSmallInteger('planned_sessions')->nullable();
            $table->text('resources_methods_techniques')->nullable();
            $table->text('therapeutic_objectives')->nullable();
            $table->text('physical_therapy_prognosis')->nullable();
            $table->timestamps();
            $table->index(['patient_id', 'assessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_assessments');
    }
};
