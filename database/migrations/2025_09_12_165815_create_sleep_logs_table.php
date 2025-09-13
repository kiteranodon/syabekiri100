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
        Schema::create('sleep_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_log_id')->constrained()->onDelete('cascade');
            $table->time('bedtime')->nullable();
            $table->time('wakeup_time')->nullable();
            $table->decimal('sleep_hours', 4, 2)->nullable()->comment('Auto-calculated or manual input');
            $table->integer('sleep_quality')->nullable()->comment('1-5 scale');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sleep_logs');
    }
};
