<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('access_group_permission', function (Blueprint $table): void {
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['access_group_id', 'permission_id']);
        });

        Schema::create('access_group_user', function (Blueprint $table): void {
            $table->foreignId('access_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['access_group_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_group_user');
        Schema::dropIfExists('access_group_permission');
    }
};
