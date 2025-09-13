<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appointment_date',
        'from_date',
        'to_date',
        'avg_mood',
        'mood_trend',
        'avg_sleep_hours',
        'medication_adherence',
        'symptom_summary',
        'free_summary',
        'pdf_path',
        'previous_report_id',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'from_date' => 'date',
        'to_date' => 'date',
        'avg_mood' => 'decimal:2',
        'avg_sleep_hours' => 'decimal:2',
        'medication_adherence' => 'decimal:2',
        'symptom_summary' => 'array',
    ];

    /**
     * ユーザーとのリレーションシップ
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 前回のレポートとのリレーションシップ
     */
    public function previousReport(): BelongsTo
    {
        return $this->belongsTo(Report::class, 'previous_report_id');
    }

    /**
     * 次のレポートとのリレーションシップ
     */
    public function nextReport(): HasOne
    {
        return $this->hasOne(Report::class, 'previous_report_id');
    }

    /**
     * 診察予約とのリレーションシップ
     */
    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    /**
     * 最新のレポートのスコープ
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('appointment_date', 'desc');
    }
}
