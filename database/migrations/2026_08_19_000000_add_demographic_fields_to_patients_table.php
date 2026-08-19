<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->string('indication')->nullable()->after('phone');
            $table->string('birthplace')->nullable()->after('indication');
            $table->string('marital_status')->nullable()->after('birthplace');
            $table->string('gender', 100)->nullable()->after('marital_status');
            $table->string('profession')->nullable()->after('gender');
            $table->string('address')->nullable()->after('profession');
        });

        DB::table('patients')->orderBy('id')->chunkById(100, function ($patients): void {
            foreach ($patients as $patient) {
                $assessment = DB::table('patient_assessments')
                    ->where('patient_id', $patient->id)
                    ->latest('assessed_at')
                    ->latest('id')
                    ->first();

                if (! $assessment) {
                    continue;
                }

                $values = array_filter([
                    'indication' => $assessment->indication,
                    'birthplace' => $assessment->birthplace,
                    'marital_status' => $assessment->marital_status,
                    'gender' => $assessment->gender,
                    'profession' => $assessment->profession,
                    'address' => $assessment->address,
                ], fn (mixed $value): bool => $value !== null);

                if ($values !== []) {
                    DB::table('patients')->where('id', $patient->id)->update($values);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropColumn([
                'indication',
                'birthplace',
                'marital_status',
                'gender',
                'profession',
                'address',
            ]);
        });
    }
};
