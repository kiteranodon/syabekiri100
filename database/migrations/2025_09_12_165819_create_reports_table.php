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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('appointment_date');
            $table->date('from_date');
            $table->date('to_date');
            $table->decimal('avg_mood', 3, 2)->nullable();
            $table->enum('mood_trend', ['上昇', '下降', '安定'])->nullable();
            $table->decimal('avg_sleep_hours', 4, 2)->nullable();
            $table->decimal('medication_adherence', 5, 2)->nullable()->comment('Percentage');
            $table->json('symptom_summary')->nullable()->comment('TOP3 symptoms etc.');
            $table->text('free_summary')->nullable()->comment('User wants to tell doctor');
            $table->string('pdf_path')->nullable();
            $table->foreignId('previous_report_id')->nullable()->constrained('reports')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
