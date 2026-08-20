<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['patient_assessments', 'patient_evolutions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->text('cancellation_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['patient_assessments', 'patient_evolutions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('cancelled_by');
                $table->dropColumn(['cancelled_at', 'cancellation_reason']);
            });
        }
    }
};
