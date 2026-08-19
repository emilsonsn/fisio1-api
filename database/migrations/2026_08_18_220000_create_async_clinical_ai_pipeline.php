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
                $table->string('status')->default('completed')->index();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('confirmed_at')->nullable();
            });
        }

        Schema::create('clinical_ai_processes', function (Blueprint $table): void {
            $table->id();
            $table->morphs('processable');
            $table->string('status')->default('pending')->index();
            $table->string('audio_disk')->default('local');
            $table->string('audio_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('chunks_count')->default(0);
            $table->unsignedInteger('processed_chunks')->default(0);
            $table->longText('transcript')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('clinical_ai_chunks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinical_ai_process_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('status')->default('pending')->index();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('mime_type')->default('audio/ogg');
            $table->unsignedInteger('attempts')->default(0);
            $table->longText('transcript')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->unique(['clinical_ai_process_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinical_ai_chunks');
        Schema::dropIfExists('clinical_ai_processes');

        foreach (['patient_assessments', 'patient_evolutions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropConstrainedForeignId('confirmed_by');
                $table->dropColumn(['status', 'confirmed_at']);
            });
        }
    }
};
