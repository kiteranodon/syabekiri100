<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SleepLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_log_id',
        'bedtime',
        'wakeup_time',
        'sleep_hours',
        'sleep_quality',
    ];

    protected $casts = [
        'bedtime' => 'datetime:H:i',
        'wakeup_time' => 'datetime:H:i',
        'sleep_hours' => 'decimal:2',
        'sleep_quality' => 'integer',
    ];

    /**
     * 日次ログとのリレーションシップ
     */
    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    /**
     * 睡眠時間を自動計算
     */
    public function calculateSleepHours(): ?float
    {
        if (!$this->bedtime || !$this->wakeup_time) {
            return null;
        }

        $bedtime = \Carbon\Carbon::parse($this->bedtime);
        $wakeupTime = \Carbon\Carbon::parse($this->wakeup_time);

        // 翌日の起床時間の場合
        if ($wakeupTime->lt($bedtime)) {
            $wakeupTime->addDay();
        }

        return round($bedtime->diffInMinutes($wakeupTime) / 60, 2);
    }

    /**
     * 睡眠時間を「○○時間○○分」形式で取得
     */
    public function getSleepDurationFormattedAttribute(): string
    {
        if (!$this->sleep_hours) {
            return '';
        }

        $totalMinutes = round($this->sleep_hours * 60);
        $hours = floor($totalMinutes / 60);
        $minutes = $totalMinutes % 60;

        return $hours . '時間' . $minutes . '分';
    }

    /**
     * 睡眠時間を自動設定
     */
    protected static function booted()
    {
        static::saving(function ($sleepLog) {
            if ($sleepLog->bedtime && $sleepLog->wakeup_time && !$sleepLog->sleep_hours) {
                $sleepLog->sleep_hours = $sleepLog->calculateSleepHours();
            }
        });
    }
}
