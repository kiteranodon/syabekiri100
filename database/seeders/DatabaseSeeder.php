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
        // 過去30日分の日次ログを作成
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();

            $dailyLog = \App\Models\DailyLog::create([
                'user_id' => $user->id,
                'date' => $date,
                'mood_score' => rand(1, 5),
                'free_note' => '今日は' . ['良い一日でした', '普通の日でした', '少し疲れました', '体調が良くありませんでした', '気分が沈んでいました'][rand(0, 4)],
            ]);

            // 睡眠ログを作成（80%の確率で）
            if (rand(1, 100) <= 80) {
                $bedtime = sprintf('%02d:%02d', rand(21, 23), rand(0, 59));
                $wakeupTime = sprintf('%02d:%02d', rand(6, 8), rand(0, 59));

                \App\Models\SleepLog::create([
                    'daily_log_id' => $dailyLog->id,
                    'bedtime' => $bedtime,
                    'wakeup_time' => $wakeupTime,
                    'sleep_quality' => rand(1, 5),
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
                \App\Models\MedicationLog::create([
                    'daily_log_id' => $dailyLog->id,
                    'medicine_name' => $medication['name'],
                    'timing' => $medication['timing'],
                    'taken' => rand(0, 100) <= 85, // 85%の服薬率
                ]);
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
}
