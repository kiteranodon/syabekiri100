<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MedicationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_log_id',
        'medicine_name',
        'timing',
        'taken',
    ];

    protected $casts = [
        'taken' => 'boolean',
    ];

    /**
     * 日次ログとのリレーションシップ
     */
    public function dailyLog(): BelongsTo
    {
        return $this->belongsTo(DailyLog::class);
    }

    /**
     * 服薬済みのスコープ
     */
    public function scopeTaken($query)
    {
        return $query->where('taken', true);
    }

    /**
     * 未服薬のスコープ
     */
    public function scopeNotTaken($query)
    {
        return $query->where('taken', false);
    }

    /**
     * 服薬タイミングの日本語表示
     */
    public function getTimingDisplayAttribute(): string
    {
        if (empty($this->timing)) {
            return '';
        }

        $timingLabels = [
            'morning' => '朝',
            'afternoon' => '昼',
            'evening' => '晩',
            'bedtime' => '就寝前',
            'as_needed' => '頓服',
        ];

        return $timingLabels[$this->timing] ?? $this->timing;
    }

    /**
     * 服薬タイミングの選択肢
     */
    public static function getTimingOptions(): array
    {
        return [
            'morning' => '朝',
            'afternoon' => '昼',
            'evening' => '晩',
            'bedtime' => '就寝前',
            'as_needed' => '頓服',
        ];
    }

    /**
     * この薬の服薬予定回数を取得（頓服は除外）
     */
    public function getScheduledDosesCountAttribute(): int
    {
        // 単一のタイミングなので、頓服でなければ1、頓服なら0
        return $this->timing !== 'as_needed' ? 1 : 0;
    }

    /**
     * この薬の実際の服薬回数を取得（頓服は除外）
     */
    public function getTakenDosesCountAttribute(): int
    {
        // 頓服でなく、かつ服薬済みの場合のみ1
        return ($this->timing !== 'as_needed' && $this->taken) ? 1 : 0;
    }

    /**
     * 頓服薬かどうかを判定
     */
    public function getIsAsNeededAttribute(): bool
    {
        return $this->timing === 'as_needed';
    }

    /**
     * 定時薬かどうかを判定（頓服以外）
     */
    public function getIsRegularMedicationAttribute(): bool
    {
        return $this->timing !== 'as_needed';
    }
}
