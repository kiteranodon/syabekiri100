<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // テストユーザーの作成
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // サンプルデータの作成
        $this->createSampleData($user);
    }

    /**
     * サンプルデータを作成
     */
    private function createSampleData(User $user): void
    {
        // 過去365日分（1年間）の日次ログを作成
        for ($i = 364; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();

            // データの作成確率（90%の確率でデータを作成、10%は欠損として扱う）
            if (rand(1, 100) <= 90) {
                // 季節や時期による気分の変動を模擬
                $baseScore = $this->getMoodScoreByDate($i);
                $moodScore = max(1, min(5, $baseScore + rand(-1, 1)));

                $dailyLog = \App\Models\DailyLog::create([
                    'user_id' => $user->id,
                    'date' => $date,
                    'mood_score' => $moodScore,
                    'free_note' => $this->getRandomNote($moodScore),
                ]);

                // 睡眠ログを作成（85%の確率で）
                if (rand(1, 100) <= 85) {
                    $sleepData = $this->getSleepDataByDate($i);

                    \App\Models\SleepLog::create([
                        'daily_log_id' => $dailyLog->id,
                        'bedtime' => $sleepData['bedtime'],
                        'wakeup_time' => $sleepData['wakeup_time'],
                        'sleep_hours' => $sleepData['sleep_hours'],
                        'sleep_quality' => max(1, min(5, $moodScore + rand(-1, 1))),
                    ]);
                }

                // 服薬ログを作成（薬の種類とタイミングごとに別レコード）
                $medications = [
                    ['name' => 'リスペリドン', 'timing' => 'morning'],
                    ['name' => 'リスペリドン', 'timing' => 'evening'],
                    ['name' => 'セルトラリン', 'timing' => 'morning'],
                    ['name' => 'ロラゼパム', 'timing' => 'as_needed'],
                    ['name' => 'メラトニン', 'timing' => 'bedtime'],
                    ['name' => 'オメプラゾール', 'timing' => 'morning'],
                    ['name' => 'オメプラゾール', 'timing' => 'afternoon'],
                ];

                foreach ($medications as $medication) {
                    // 時期による服薬遵守率の変動
                    $adherenceRate = $this->getAdherenceRateByDate($i);

                    \App\Models\MedicationLog::create([
                        'daily_log_id' => $dailyLog->id,
                        'medicine_name' => $medication['name'],
                        'timing' => $medication['timing'],
                        'taken' => rand(0, 100) <= $adherenceRate,
                    ]);
                }
            }
        }

        // サンプルレポートを作成
        \App\Models\Report::create([
            'user_id' => $user->id,
            'appointment_date' => now()->addDays(7)->toDateString(),
            'from_date' => now()->subDays(29)->toDateString(),
            'to_date' => now()->toDateString(),
            'avg_mood' => 3.2,
            'mood_trend' => '安定',
            'avg_sleep_hours' => 7.5,
            'medication_adherence' => 85.0,
            'symptom_summary' => [
                'top_symptoms' => ['不安', '睡眠障害', '集中力低下'],
                'frequency' => [15, 12, 8]
            ],
            'free_summary' => '最近は薬の効果が出てきているように感じます。睡眠の質も改善されています。',
        ]);
    }

    /**
     * 日付に基づいて気分スコアのベースを取得（季節変動を模擬）
     */
    private function getMoodScoreByDate(int $daysAgo): int
    {
        $month = now()->subDays($daysAgo)->month;

        // 季節による気分の変動を模擬
        switch ($month) {
            case 12:
            case 1:
            case 2: // 冬季（やや低め）
                return rand(2, 4);
            case 3:
            case 4:
            case 5: // 春季（上昇傾向）
                return rand(3, 5);
            case 6:
            case 7:
            case 8: // 夏季（安定）
                return rand(3, 4);
            case 9:
            case 10:
            case 11: // 秋季（やや下降）
                return rand(2, 4);
            default:
                return rand(2, 4);
        }
    }

    /**
     * 気分スコアに基づいてランダムなメモを生成
     */
    private function getRandomNote(int $moodScore): string
    {
        $notes = [
            1 => ['とても辛い一日でした', '気分が沈んでいます', '何もやる気が起きません', '体調が優れません'],
            2 => ['少し疲れました', '体調があまり良くありません', 'ストレスを感じています', '不安な気持ちです'],
            3 => ['普通の日でした', '特に変わったことはありません', 'まあまあの調子です', '平凡な一日'],
            4 => ['良い一日でした', '気分が良いです', '調子が良いです', '穏やかな気持ちです'],
            5 => ['とても良い一日でした', '気分が最高です', '元気いっぱいです', 'とても幸せです'],
        ];

        return $notes[$moodScore][array_rand($notes[$moodScore])];
    }

    /**
     * 日付に基づいて睡眠データを取得
     */
    private function getSleepDataByDate(int $daysAgo): array
    {
        // 季節や曜日による睡眠パターンの変動
        $dayOfWeek = now()->subDays($daysAgo)->dayOfWeek;
        $isWeekend = in_array($dayOfWeek, [0, 6]); // 日曜日=0, 土曜日=6

        if ($isWeekend) {
            // 週末は遅寝遅起き
            $bedtimeHour = rand(22, 24);
            $wakeupHour = rand(8, 10);
        } else {
            // 平日は規則的
            $bedtimeHour = rand(21, 23);
            $wakeupHour = rand(6, 8);
        }

        $bedtime = sprintf('%02d:%02d', $bedtimeHour === 24 ? 0 : $bedtimeHour, rand(0, 59));
        $wakeupTime = sprintf('%02d:%02d', $wakeupHour, rand(0, 59));

        // 睡眠時間を計算
        $bedtimeCarbon = \Carbon\Carbon::createFromFormat('H:i', $bedtime);
        $wakeupCarbon = \Carbon\Carbon::createFromFormat('H:i', $wakeupTime);

        if ($wakeupCarbon->lt($bedtimeCarbon)) {
            $wakeupCarbon->addDay();
        }

        $sleepHours = round($bedtimeCarbon->diffInMinutes($wakeupCarbon) / 60, 1);

        return [
            'bedtime' => $bedtime,
            'wakeup_time' => $wakeupTime,
            'sleep_hours' => $sleepHours,
        ];
    }

    /**
     * 日付に基づいて服薬遵守率を取得
     */
    private function getAdherenceRateByDate(int $daysAgo): int
    {
        $month = now()->subDays($daysAgo)->month;

        // 治療開始からの経過による遵守率の変化を模擬
        if ($daysAgo > 300) {
            // 治療開始初期（低い遵守率）
            return rand(60, 75);
        } elseif ($daysAgo > 180) {
            // 治療中期（向上）
            return rand(75, 85);
        } elseif ($daysAgo > 90) {
            // 治療後期（安定）
            return rand(85, 95);
        } else {
            // 最近（良好な遵守率）
            return rand(90, 98);
        }
    }
}
