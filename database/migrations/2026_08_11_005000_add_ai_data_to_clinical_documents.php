<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_assessments', function (Blueprint $table): void {
            $table->longText('ai_transcript')->nullable()->after('physical_therapy_prognosis');
            $table->timestamp('ai_processed_at')->nullable()->after('ai_transcript');
        });
        Schema::table('patient_evolutions', function (Blueprint $table): void {
            $table->longText('ai_transcript')->nullable()->after('observations');
            $table->timestamp('ai_processed_at')->nullable()->after('ai_transcript');
        });
    }

    public function down(): void
    {
        Schema::table('patient_assessments', function (Blueprint $table): void {
            $table->dropColumn(['ai_transcript', 'ai_processed_at']);
        });
        Schema::table('patient_evolutions', function (Blueprint $table): void {
            $table->dropColumn(['ai_transcript', 'ai_processed_at']);
        });
    }
};
