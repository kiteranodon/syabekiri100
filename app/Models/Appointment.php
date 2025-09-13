<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'appointment_date',
        'doctor_name',
        'report_id',
        'memo',
    ];

    protected $casts = [
        'appointment_date' => 'date',
    ];

    /**
     * ユーザーとのリレーションシップ
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * レポートとのリレーションシップ
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /**
     * 今後の診察予定のスコープ
     */
    public function scopeUpcoming($query)
    {
        return $query->where('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date');
    }

    /**
     * 過去の診察のスコープ
     */
    public function scopePast($query)
    {
        return $query->where('appointment_date', '<', now()->toDateString())
            ->orderBy('appointment_date', 'desc');
    }
}
