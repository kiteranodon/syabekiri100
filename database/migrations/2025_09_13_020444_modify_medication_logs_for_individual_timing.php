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
        Schema::table('medication_logs', function (Blueprint $table) {
            // timingを単一の値に変更（配列ではなく）
            $table->dropColumn('timing');
            $table->string('timing')->after('medicine_name')->comment('服薬タイミング: morning, afternoon, evening, bedtime, as_needed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medication_logs', function (Blueprint $table) {
            $table->dropColumn('timing');
            $table->json('timing')->nullable()->after('medicine_name')->comment('服薬タイミング: morning, afternoon, evening, bedtime, as_needed');
        });
    }
};
