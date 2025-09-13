<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'date',
        'mood_score',
        'free_note',
    ];

    protected $casts = [
        'date' => 'date',
        'mood_score' => 'integer',
    ];

    /**
     * ユーザーとのリレーションシップ
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 睡眠ログとのリレーションシップ
     */
    public function sleepLog(): HasOne
    {
        return $this->hasOne(SleepLog::class);
    }

    /**
     * 服薬ログとのリレーションシップ
     */
    public function medicationLogs(): HasMany
    {
        return $this->hasMany(MedicationLog::class);
    }

    /**
     * 指定期間のスコープ
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * 自由日記の文字数制限チェック
     */
    public function setFreeNoteAttribute($value)
    {
        $this->attributes['free_note'] = $value ? mb_substr($value, 0, 140) : null;
    }
}
