<?php

use function Livewire\Volt\{state, computed, mount};
use App\Models\DailyLog;
use App\Models\SleepLog;
use App\Models\MedicationLog;

state([
    'dateFrom' => null,
    'dateTo' => null,
    'selectedPeriod' => '1month', // デフォルトは1ヶ月
]);

mount(function () {
    // デフォルトで過去1ヶ月のデータを表示
    $this->setPeriod('1month');
});

// 期間選択メソッド
$setPeriod = function ($period) {
    $this->selectedPeriod = $period;
    $this->dateTo = now()->toDateString();

    switch ($period) {
        case '1week':
            $this->dateFrom = now()->subWeek()->toDateString();
            break;
        case '2weeks':
            $this->dateFrom = now()->subWeeks(2)->toDateString();
            break;
        case '3weeks':
            $this->dateFrom = now()->subWeeks(3)->toDateString();
            break;
        case '1month':
            $this->dateFrom = now()->subMonth()->toDateString();
            break;
        case '3months':
            $this->dateFrom = now()->subMonths(3)->toDateString();
            break;
        case '6months':
            $this->dateFrom = now()->subMonths(6)->toDateString();
            break;
        case '1year':
            $this->dateFrom = now()->subYear()->toDateString();
            break;
        default:
            $this->dateFrom = now()->subMonth()->toDateString();
    }

    // チャートデータ更新をブラウザに通知
    $this->dispatch('chartDataUpdated', [
        'chartData' => $this->chartData,
        'period' => $period,
    ]);
};

// 期間タブの定義
$periodTabs = computed(function () {
    return [
        '1week' => '1週間',
        '2weeks' => '2週間',
        '3weeks' => '3週間',
        '1month' => '1ヶ月',
        '3months' => '3ヶ月',
        '6months' => '6ヶ月',
        '1year' => '1年',
    ];
});

// 手動で日付を変更した時の処理
$updatedDateFrom = function () {
    $this->selectedPeriod = 'custom'; // カスタム期間に設定

    // チャートデータ更新をブラウザに通知
    $this->dispatch('chartDataUpdated', [
        'chartData' => $this->chartData,
        'period' => 'custom',
    ]);
};

$updatedDateTo = function () {
    $this->selectedPeriod = 'custom'; // カスタム期間に設定

    // チャートデータ更新をブラウザに通知
    $this->dispatch('chartDataUpdated', [
        'chartData' => $this->chartData,
        'period' => 'custom',
    ]);
};

// 指定期間の日次ログデータを取得
$chartData = computed(function () {
    $logs = DailyLog::where('user_id', auth()->id())
        ->whereBetween('date', [$this->dateFrom, $this->dateTo])
        ->with(['sleepLog'])
        ->orderBy('date', 'asc')
        ->get();

    // データを整形 - データのない日はスキップ
    $chartData = [
        'labels' => [],
        'moodData' => [],
        'sleepData' => [],
    ];

    foreach ($logs as $log) {
        // 気分スコアまたは睡眠データがある場合のみ追加
        if ($log->mood_score || $log->sleepLog) {
            // 日付ラベル（MM/DD形式）
            $chartData['labels'][] = $log->date->format('m/d');

            // 気分スコア（1-5、データがない場合はnull）
            $chartData['moodData'][] = $log->mood_score;

            // 睡眠時間（データがない場合はnull）
            $chartData['sleepData'][] = $log->sleepLog?->sleep_hours;
        }
    }

    return $chartData;
});

// 統計情報を計算
$statistics = computed(function () {
    $logs = DailyLog::where('user_id', auth()->id())
        ->whereBetween('date', [$this->dateFrom, $this->dateTo])
        ->with(['sleepLog', 'medicationLogs'])
        ->get();

    $moodScores = $logs->whereNotNull('mood_score')->pluck('mood_score');
    $sleepHours = $logs->map(fn($log) => $log->sleepLog?->sleep_hours)->filter();

    // 服薬遵守率（頓服薬を除く）
    $regularMedications = $logs->flatMap(fn($log) => $log->medicationLogs)->where('timing', '!=', 'as_needed');
    $totalScheduled = $regularMedications->count();
    $totalTaken = $regularMedications->where('taken', true)->count();
    $adherenceRate = $totalScheduled > 0 ? ($totalTaken / $totalScheduled) * 100 : 0;

    return [
        'period_days' => $logs->count(),
        'mood_avg' => $moodScores->avg(),
        'mood_max' => $moodScores->max(),
        'mood_min' => $moodScores->min(),
        'sleep_avg' => $sleepHours->avg(),
        'sleep_max' => $sleepHours->max(),
        'sleep_min' => $sleepHours->min(),
        'adherence_rate' => round($adherenceRate, 1),
        'total_entries' => $logs->whereNotNull('mood_score')->count(),
    ];
});

// ユーザー状態要約を生成
$userSummary = computed(function () {
    $logs = DailyLog::where('user_id', auth()->id())
        ->whereBetween('date', [$this->dateFrom, $this->dateTo])
        ->with(['sleepLog', 'medicationLogs'])
        ->orderBy('date', 'desc')
        ->get();

    if ($logs->isEmpty()) {
        return '選択された期間にはデータが記録されていません。';
    }

    $statistics = $this->statistics;
    $periodName = $this->selectedPeriod === 'custom' ? 'カスタム期間' : $this->periodTabs[$this->selectedPeriod];

    // 自由日記の分析
    $diaryNotes = $logs->whereNotNull('free_note')->pluck('free_note');
    $moodTrend = $this->analyzeMoodTrend($logs);
    $sleepPattern = $this->analyzeSleepPattern($logs);
    $diaryInsights = $this->analyzeDiaryContent($diaryNotes);

    return $this->generateSummary($periodName, $statistics, $moodTrend, $sleepPattern, $diaryInsights);
});

// 気分の傾向を分析
$analyzeMoodTrend = function ($logs) {
    $moodScores = $logs->whereNotNull('mood_score')->pluck('mood_score', 'date')->sortKeys();

    if ($moodScores->count() < 2) {
        return '安定';
    }

    $recent = $moodScores->take(-7)->avg(); // 最近7日の平均
    $earlier = $moodScores->take(7)->avg(); // 最初7日の平均

    $diff = $recent - $earlier;

    if ($diff > 0.5) {
        return '改善傾向';
    } elseif ($diff < -0.5) {
        return '下降傾向';
    } else {
        return '安定';
    }
};

// 睡眠パターンを分析
$analyzeSleepPattern = function ($logs) {
    $sleepLogs = $logs->filter(fn($log) => $log->sleepLog)->map(fn($log) => $log->sleepLog);

    if ($sleepLogs->isEmpty()) {
        return '睡眠データが不足しています';
    }

    $avgHours = $sleepLogs->avg('sleep_hours');
    $avgQuality = $sleepLogs->avg('sleep_quality');

    if ($avgHours >= 7 && $avgQuality >= 4) {
        return '良好な睡眠状態';
    } elseif ($avgHours >= 6 && $avgQuality >= 3) {
        return '普通の睡眠状態';
    } else {
        return '睡眠の改善が必要';
    }
};

// 日記内容を分析
$analyzeDiaryContent = function ($diaryNotes) {
    if ($diaryNotes->isEmpty()) {
        return ['sentiment' => 'neutral', 'keywords' => []];
    }

    $allText = $diaryNotes->implode(' ');

    // ポジティブ・ネガティブキーワード分析
    $positiveWords = ['良い', '元気', '幸せ', '調子', '穏やか', '最高', '満足', '楽しい', '嬉しい'];
    $negativeWords = ['辛い', '疲れ', '沈ん', '不安', 'ストレス', '体調', '優れ', 'やる気'];

    $positiveCount = 0;
    $negativeCount = 0;
    $foundKeywords = [];

    foreach ($positiveWords as $word) {
        $count = substr_count($allText, $word);
        if ($count > 0) {
            $positiveCount += $count;
            $foundKeywords[] = $word;
        }
    }

    foreach ($negativeWords as $word) {
        $count = substr_count($allText, $word);
        if ($count > 0) {
            $negativeCount += $count;
            $foundKeywords[] = $word;
        }
    }

    $sentiment = 'neutral';
    if ($positiveCount > $negativeCount * 1.5) {
        $sentiment = 'positive';
    } elseif ($negativeCount > $positiveCount * 1.5) {
        $sentiment = 'negative';
    }

    return [
        'sentiment' => $sentiment,
        'keywords' => array_slice(array_unique($foundKeywords), 0, 5),
        'positive_count' => $positiveCount,
        'negative_count' => $negativeCount,
    ];
};

// 要約文を生成
$generateSummary = function ($periodName, $statistics, $moodTrend, $sleepPattern, $diaryInsights) {
    $summary = "{$periodName}の記録を分析した結果、";

    // 気分の状況
    if ($statistics['mood_avg']) {
        $moodLevel = '';
        if ($statistics['mood_avg'] >= 4) {
            $moodLevel = '良好';
        } elseif ($statistics['mood_avg'] >= 3) {
            $moodLevel = '安定';
        } else {
            $moodLevel = 'やや低調';
        }
        $summary .= "気分は{$moodLevel}で{$moodTrend}を示しています。";
    }

    // 睡眠の状況
    if ($statistics['sleep_avg']) {
        $sleepHours = round($statistics['sleep_avg'], 1);
        $summary .= "睡眠時間は平均{$sleepHours}時間で、{$sleepPattern}です。";
    }

    // 服薬の状況
    if ($statistics['adherence_rate'] > 0) {
        $adherenceLevel = '';
        if ($statistics['adherence_rate'] >= 90) {
            $adherenceLevel = '良好';
        } elseif ($statistics['adherence_rate'] >= 75) {
            $adherenceLevel = '概ね良好';
        } else {
            $adherenceLevel = '改善の余地';
        }
        $summary .= "服薬遵守率は{$statistics['adherence_rate']}%で{$adherenceLevel}です。";
    }

    // 日記の内容分析
    $sentiment = $diaryInsights['sentiment'];
    $keywords = $diaryInsights['keywords'];

    if ($sentiment === 'positive') {
        $summary .= '日記からは前向きな気持ちが多く感じられ、';
    } elseif ($sentiment === 'negative') {
        $summary .= '日記からは不安や疲労感が表れており、';
    } else {
        $summary .= '日記の内容は比較的安定しており、';
    }

    if (!empty($keywords)) {
        $keywordStr = implode('、', array_slice($keywords, 0, 3));
        $summary .= "「{$keywordStr}」といった表現が特徴的です。";
    } else {
        $summary .= '日常的な内容が記録されています。';
    }

    // 総合的な評価
    $overallScore = 0;
    if ($statistics['mood_avg']) {
        $overallScore += ($statistics['mood_avg'] / 5) * 30;
    }
    if ($statistics['sleep_avg']) {
        $overallScore += min($statistics['sleep_avg'] / 8, 1) * 30;
    }
    if ($statistics['adherence_rate']) {
        $overallScore += ($statistics['adherence_rate'] / 100) * 40;
    }

    if ($overallScore >= 80) {
        $summary .= '全体的に良好な状態を維持されています。';
    } elseif ($overallScore >= 60) {
        $summary .= '概ね安定した状態ですが、さらなる改善の可能性があります。';
    } else {
        $summary .= '体調管理により一層の注意を払うことをお勧めします。';
    }

    return mb_substr($summary, 0, 300);
};

?>

<div>
    <!-- ページヘッダー -->
    <div class="bg-white shadow-sm border-b border-gray-200 mb-6 no-print">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-6">
                <h1 class="text-2xl font-bold text-gray-900">
                    {{ __('レポート') }}
                </h1>
                <button onclick="printReport()"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    印刷
                </button>
            </div>
        </div>
    </div>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- 期間選択（詳細設定） -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6 no-print">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900">期間設定</h3>
                        <div class="text-sm text-gray-600">
                            現在の期間: <span class="font-medium text-blue-600">
                                {{ $selectedPeriod === 'custom' ? 'カスタム' : $this->periodTabs[$selectedPeriod] }}
                            </span>
                            ({{ \Carbon\Carbon::parse($dateFrom)->format('Y/m/d') }} ～
                            {{ \Carbon\Carbon::parse($dateTo)->format('Y/m/d') }})
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="dateFrom" class="block text-sm font-medium text-gray-700 mb-2">開始日（手動調整）</label>
                            <input type="date" id="dateFrom" wire:model.live="dateFrom"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                        <div>
                            <label for="dateTo" class="block text-sm font-medium text-gray-700 mb-2">終了日（手動調整）</label>
                            <input type="date" id="dateTo" wire:model.live="dateTo"
                                max="{{ now()->toDateString() }}"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        ※ 上記のタブで期間を選択するか、こちらで手動で日付を調整できます
                    </p>
                </div>
            </div>

            <!-- グラフ表示 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 bg-white border-b border-gray-200">
                    <!-- 期間選択タブ -->
                    <div class="flex flex-wrap justify-center gap-1 mb-6 no-print">
                        @foreach ($this->periodTabs as $key => $label)
                            <button wire:click="setPeriod('{{ $key }}')" wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed" wire:target="setPeriod"
                                class="px-4 py-2 text-sm font-medium rounded-lg transition-colors duration-200 
                                    {{ $selectedPeriod === $key
                                        ? 'bg-blue-600 text-white shadow-md'
                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                                <span wire:loading.remove wire:target="setPeriod">{{ $label }}</span>
                                <span wire:loading wire:target="setPeriod" class="flex items-center">
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-current"
                                        xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                            stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                    {{ $label }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    <h3 class="text-lg font-medium text-gray-900 mb-6 text-center">気分と睡眠の推移</h3>
                    <div class="relative h-[600px]">
                        <!-- ローディング表示 -->
                        <div wire:loading wire:target="setPeriod,updatedDateFrom,updatedDateTo"
                            class="absolute inset-0 bg-white bg-opacity-75 flex items-center justify-center z-10">
                            <div class="text-center">
                                <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                        stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                <p class="text-sm text-gray-600">チャートを更新中...</p>
                            </div>
                        </div>
                        <canvas id="moodSleepChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- 統計情報 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">統計情報</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- 気分統計 -->
                        <div class="text-center">
                            <div class="bg-blue-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-blue-900 mb-2">気分スコア</h4>
                                @if ($this->statistics['mood_avg'])
                                    <p class="text-2xl font-semibold text-blue-600 flex items-center justify-center">
                                        <x-mood-icon :score="round($this->statistics['mood_avg'])" size="2xl" />
                                        <span
                                            class="ml-2">{{ number_format($this->statistics['mood_avg'], 1) }}</span>
                                    </p>
                                    <p class="text-xs text-blue-700 mt-1">
                                        最高: {{ $this->statistics['mood_max'] }} / 最低:
                                        {{ $this->statistics['mood_min'] }}
                                    </p>
                                @else
                                    <p class="text-2xl font-semibold text-gray-400">なし</p>
                                @endif
                            </div>
                        </div>

                        <!-- 睡眠統計 -->
                        <div class="text-center">
                            <div class="bg-green-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-green-900 mb-2">平均睡眠時間</h4>
                                @if ($this->statistics['sleep_avg'])
                                    @php
                                        $avgSleep = $this->statistics['sleep_avg'];
                                        $totalMinutes = round($avgSleep * 60);
                                        $hours = floor($totalMinutes / 60);
                                        $minutes = $totalMinutes % 60;
                                    @endphp
                                    <p class="text-2xl font-semibold text-green-600">
                                        {{ $hours }}時間{{ $minutes }}分</p>
                                    <p class="text-xs text-green-700 mt-1">
                                        最長: {{ number_format($this->statistics['sleep_max'], 1) }}h /
                                        最短: {{ number_format($this->statistics['sleep_min'], 1) }}h
                                    </p>
                                @else
                                    <p class="text-2xl font-semibold text-gray-400">なし</p>
                                @endif
                            </div>
                        </div>

                        <!-- 服薬遵守率 -->
                        <div class="text-center">
                            <div class="bg-purple-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-purple-900 mb-2">服薬遵守率</h4>
                                <p class="text-2xl font-semibold text-purple-600">
                                    {{ $this->statistics['adherence_rate'] }}%</p>
                                <p class="text-xs text-purple-700 mt-1">定時薬のみ</p>
                            </div>
                        </div>

                        <!-- 記録日数 -->
                        <div class="text-center">
                            <div class="bg-orange-50 rounded-lg p-4">
                                <h4 class="text-sm font-medium text-orange-900 mb-2">記録日数</h4>
                                <p class="text-2xl font-semibold text-orange-600">
                                    {{ $this->statistics['total_entries'] }}日</p>
                                <p class="text-xs text-orange-700 mt-1">{{ $this->statistics['period_days'] }}日中</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ユーザー状態要約 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-indigo-600" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        状態要約
                    </h3>
                    <div class="bg-gradient-to-r from-indigo-50 to-purple-50 rounded-lg p-6 border border-indigo-200">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                            </div>
                            <div class="ml-4 flex-1">
                                <h4 class="text-base font-medium text-gray-900 mb-2">
                                    {{ $selectedPeriod === 'custom' ? 'カスタム期間' : $this->periodTabs[$selectedPeriod] }}の総合分析
                                </h4>
                                <p class="text-gray-700 leading-relaxed">
                                    {{ $this->userSummary }}
                                </p>
                                <div class="mt-3 text-xs text-gray-500">
                                    ※ この要約は記録された気分・睡眠・服薬データと自由日記の内容を分析して生成されています
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <!-- Chart.js初期化スクリプト -->
    <script>
        // グローバル変数
        window.moodSleepChart = null;
        window.chartData = null;

        // チャート作成関数
        function createMoodSleepChart(customData = null) {
            console.log('=== Chart creation started ===');

            // Chart.js確認
            if (typeof Chart === 'undefined') {
                console.error('Chart.js not loaded');
                return;
            }

            // Canvas取得
            const canvas = document.getElementById('moodSleepChart');
            if (!canvas) {
                console.error('Canvas not found');
                return;
            }

            // コンテナのサイズを確認
            const container = canvas.parentElement;
            if (container) {
                const containerRect = container.getBoundingClientRect();
                console.log('Container size:', {
                    width: containerRect.width,
                    height: containerRect.height,
                    visible: containerRect.width > 0 && containerRect.height > 0
                });

                // コンテナが見えない場合は少し待つ
                if (containerRect.width === 0 || containerRect.height === 0) {
                    console.log('Container not visible yet, retrying...');
                    setTimeout(() => createMoodSleepChart(customData), 200);
                    return false;
                }
            }

            // 既存チャート破棄
            if (window.moodSleepChart) {
                try {
                    window.moodSleepChart.destroy();
                } catch (e) {
                    console.log('Chart destroy error (ignored)');
                }
                window.moodSleepChart = null;
            }

            // Canvasのサイズをリセット
            canvas.style.width = '';
            canvas.style.height = '';
            canvas.width = 0;
            canvas.height = 0;

            // データ取得（カスタムデータがある場合はそれを使用）
            const chartData = customData || @json($this->chartData);
            console.log('Chart data:', chartData);

            // データ検証
            if (!chartData || typeof chartData !== 'object') {
                console.error('Invalid chart data:', chartData);
                return false;
            }

            let labels = Array.isArray(chartData.labels) ? chartData.labels : [];
            let moodData = Array.isArray(chartData.moodData) ? chartData.moodData : [];
            let sleepData = Array.isArray(chartData.sleepData) ? chartData.sleepData : [];

            console.log('Processing data:', {
                labelsCount: labels.length,
                moodDataCount: moodData.length,
                sleepDataCount: sleepData.length,
                labels: labels,
                moodData: moodData,
                sleepData: sleepData
            });

            // データが空の場合の処理
            if (labels.length === 0) {
                console.log('No data available for chart');
                // 空のチャートを表示
                labels = ['データなし'];
                moodData = [null];
                sleepData = [null];
            }

            // チャート作成
            try {
                const ctx = canvas.getContext('2d');
                window.moodSleepChart = new Chart(ctx, {
                    type: 'line', // Mixed chartのベースタイプを指定
                    data: {
                        labels: labels,
                        datasets: [{
                                type: 'line',
                                label: '気分スコア',
                                data: moodData,
                                borderColor: '#3b82f6',
                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                borderWidth: 3,
                                pointRadius: 6,
                                pointHoverRadius: 8,
                                pointBackgroundColor: '#3b82f6',
                                pointBorderColor: '#ffffff',
                                pointBorderWidth: 2,
                                yAxisID: 'y',
                                spanGaps: true,
                                tension: 0.3,
                                fill: false
                            },
                            {
                                type: 'bar',
                                label: '睡眠時間',
                                data: sleepData,
                                backgroundColor: 'rgba(34, 197, 94, 0.8)',
                                borderColor: '#22c55e',
                                borderWidth: 1,
                                yAxisID: 'y1'
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        resizeDelay: 0, // リサイズの遅延を最小に
                        layout: {
                            padding: {
                                top: 20,
                                right: 20,
                                bottom: 20,
                                left: 20
                            }
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: '気分スコアと睡眠時間の推移',
                                font: {
                                    size: 20,
                                    weight: 'bold'
                                },
                                padding: {
                                    top: 10,
                                    bottom: 30
                                }
                            },
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 14
                                    }
                                }
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(0, 0, 0, 0.8)',
                                titleColor: '#ffffff',
                                bodyColor: '#ffffff',
                                borderColor: 'rgba(255, 255, 255, 0.2)',
                                borderWidth: 1,
                                callbacks: {
                                    label: function(context) {
                                        let label = context.dataset.label;
                                        if (context.parsed.y !== null && context.parsed.y !== undefined) {
                                            if (context.dataset.label === '気分スコア') {
                                                label += ': ' + context.parsed.y + '/5';
                                            } else if (context.dataset.label === '睡眠時間') {
                                                const hours = Math.floor(context.parsed.y);
                                                const minutes = Math.round((context.parsed.y - hours) * 60);
                                                label += ': ' + hours + '時間' + (minutes > 0 ? minutes + '分' :
                                                    '');
                                            }
                                        } else {
                                            label += ': 記録なし';
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                title: {
                                    display: true,
                                    text: '日付',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.1)'
                                }
                            },
                            y: {
                                type: 'linear',
                                display: true,
                                position: 'left',
                                title: {
                                    display: true,
                                    text: '気分スコア',
                                    color: '#3b82f6',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                min: 0,
                                max: 6,
                                ticks: {
                                    stepSize: 1,
                                    color: '#3b82f6',
                                    font: {
                                        size: 12
                                    },
                                    callback: function(value) {
                                        return value <= 5 ? value : '';
                                    }
                                },
                                grid: {
                                    color: 'rgba(59, 130, 246, 0.2)'
                                }
                            },
                            y1: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                title: {
                                    display: true,
                                    text: '睡眠時間（時間）',
                                    color: '#22c55e',
                                    font: {
                                        size: 14,
                                        weight: 'bold'
                                    }
                                },
                                min: 0,
                                max: 14,
                                ticks: {
                                    stepSize: 2,
                                    color: '#22c55e',
                                    font: {
                                        size: 12
                                    },
                                    callback: function(value) {
                                        return value <= 12 ? value : '';
                                    }
                                },
                                grid: {
                                    drawOnChartArea: false
                                }
                            }
                        }
                    }
                });

                // チャート作成後にリサイズを強制実行
                setTimeout(() => {
                    if (window.moodSleepChart) {
                        window.moodSleepChart.resize();
                        console.log('Chart resized after creation');
                    }
                }, 100);

                // ResizeObserverでコンテナサイズ変更を監視
                if (typeof ResizeObserver !== 'undefined' && container) {
                    if (window.chartResizeObserver) {
                        window.chartResizeObserver.disconnect();
                    }

                    window.chartResizeObserver = new ResizeObserver((entries) => {
                        if (window.moodSleepChart) {
                            console.log('Container resized, updating chart');
                            window.moodSleepChart.resize();
                        }
                    });

                    window.chartResizeObserver.observe(container);
                }

                console.log('✓ Chart created successfully');
                return true;

            } catch (error) {
                console.error('Chart creation failed:', error);
                return false;
            }
        }

        // 初期化処理
        function initChart() {
            console.log('Chart initialization started, document ready state:', document.readyState);

            function attemptChartCreation(retryCount = 0) {
                const maxRetries = 5;

                if (retryCount >= maxRetries) {
                    console.error('Failed to create chart after', maxRetries, 'attempts');
                    return;
                }

                const canvas = document.getElementById('moodSleepChart');
                if (!canvas) {
                    console.log('Canvas not ready, retrying...', retryCount + 1);
                    setTimeout(() => attemptChartCreation(retryCount + 1), 500);
                    return;
                }

                if (typeof Chart === 'undefined') {
                    console.log('Chart.js not ready, retrying...', retryCount + 1);
                    setTimeout(() => attemptChartCreation(retryCount + 1), 500);
                    return;
                }

                console.log('Creating chart, attempt:', retryCount + 1);
                const success = createMoodSleepChart();

                if (success) {
                    // 初期化成功後、追加のリサイズを実行
                    setTimeout(() => {
                        if (window.moodSleepChart) {
                            window.moodSleepChart.resize();
                            console.log('Initial chart resize completed');
                        }
                    }, 300);
                } else if (retryCount < maxRetries - 1) {
                    console.log('Chart creation failed, retrying...', retryCount + 1);
                    setTimeout(() => attemptChartCreation(retryCount + 1), 1000);
                }
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => attemptChartCreation(), 500);
                });
            } else {
                setTimeout(() => attemptChartCreation(), 200);
            }
        }

        // チャートデータ更新関数
        function updateChartData(newData) {
            console.log('=== Chart data update started ===');
            console.log('New data:', newData);

            // データ検証
            if (!newData || typeof newData !== 'object') {
                console.error('Invalid update data:', newData);
                return;
            }

            if (!window.moodSleepChart) {
                console.log('Chart not found, creating new chart');
                createMoodSleepChart(newData);
                return;
            }

            try {
                // データ準備
                let labels = Array.isArray(newData.labels) ? newData.labels : [];
                let moodData = Array.isArray(newData.moodData) ? newData.moodData : [];
                let sleepData = Array.isArray(newData.sleepData) ? newData.sleepData : [];

                // データが空の場合の処理
                if (labels.length === 0) {
                    labels = ['データなし'];
                    moodData = [null];
                    sleepData = [null];
                }

                // データ更新
                const chart = window.moodSleepChart;
                chart.data.labels = labels;
                chart.data.datasets[0].data = moodData;
                chart.data.datasets[1].data = sleepData;

                // アニメーション付きで更新
                chart.update('active');

                // 更新後にリサイズを実行
                setTimeout(() => {
                    if (window.moodSleepChart) {
                        window.moodSleepChart.resize();
                        console.log('Chart resized after data update');
                    }
                }, 100);

                console.log('✓ Chart data updated successfully');
            } catch (error) {
                console.error('Chart update failed, recreating:', error);
                createMoodSleepChart(newData);
            }
        }

        // イベントリスナーの重複を防ぐフラグ
        let chartEventListenerAdded = false;

        // Livewireイベントリスナー
        function setupChartEventListeners() {
            if (chartEventListenerAdded) {
                return;
            }

            // チャートデータ更新イベント
            Livewire.on('chartDataUpdated', function(data) {
                console.log('Chart data updated event received:', data);
                const chartData = data[0]?.chartData || data.chartData;
                const period = data[0]?.period || data.period;

                console.log('Updating chart for period:', period);
                // 少し遅延させて確実にDOMが更新されてから実行
                setTimeout(() => {
                    updateChartData(chartData);
                }, 100);
            });

            chartEventListenerAdded = true;
        }

        // Livewire初期化時
        document.addEventListener('livewire:init', function() {
            setupChartEventListeners();
        });

        // Livewire更新時の処理（期間変更以外の更新時のみ）
        document.addEventListener('livewire:updated', function(event) {
            console.log('Livewire updated - checking if chart recreation needed');

            // チャートが存在しない場合のみ再作成
            if (!window.moodSleepChart) {
                console.log('Chart not found, recreating...');
                setTimeout(createMoodSleepChart, 200);
            }
        });

        // ウィンドウリサイズイベント
        let resizeTimeout;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                if (window.moodSleepChart) {
                    console.log('Window resized, updating chart');
                    window.moodSleepChart.resize();
                }
            }, 250);
        });

        // 初期化実行
        initChart();
    </script>

    <!-- 印刷用スタイル -->
    <style>
        @media print {
            @page {
                size: A4 landscape;
                margin: 5mm 8mm;
            }

            html,
            body {
                font-size: 11px !important;
                line-height: 1.3 !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden;
            }

            /* サイドバーとナビゲーションを隠す */
            .no-print,
            nav,
            aside,
            header,
            .sidebar,
            [role="navigation"],
            .navigation,
            .main-sidebar,
            .navbar-nav,
            .sidebar-dark-primary,
            .main-header,
            .user-panel,
            .brand-link,
            .min-h-screen>div:first-child {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
                width: 0 !important;
                height: 0 !important;
                margin: 0 !important;
                padding: 0 !important;
                position: absolute !important;
                left: -9999px !important;
                top: -9999px !important;
            }

            /* メインコンテンツのリセット */
            body,
            .wrapper,
            .content-wrapper,
            .main-content,
            .container,
            main,
            #app {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                transform: translateX(0) !important;
                position: static !important;
            }

            /* 印刷対象の要素を表示 */
            .print-only,
            .print-container,
            .print-left,
            .print-right,
            #chart-section,
            #statistics-section,
            #summary-section,
            html,
            body,
            main,
            .main,
            .content,
            .py-12,
            .max-w-7xl,
            .mx-auto {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: static !important;
                width: auto !important;
                height: auto !important;
            }

            .print-container {
                display: flex !important;
                max-height: 190mm;
                overflow: hidden;
                gap: 8px;
            }

            .print-left {
                display: flex !important;
                flex: 1.5;
                flex-direction: column;
                gap: 4px;
            }

            .print-right {
                flex: 0.5;
            }

            #chart-section {
                height: 70%;
                overflow: visible !important;
            }

            #moodSleepChart {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                max-height: 120mm;
            }

            #statistics-section {
                height: 30%;
            }

            #summary-section {
                height: 100%;
            }

            canvas {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .grid,
            .grid-cols-4 {
                display: grid !important;
            }

            .grid-cols-4 {
                grid-template-columns: repeat(4, 1fr) !important;
            }

            /* フォントサイズ調整 */
            .text-2xl {
                font-size: 12px !important;
            }

            .text-lg {
                font-size: 10px !important;
            }

            .text-sm {
                font-size: 8px !important;
            }

            .text-xs {
                font-size: 7px !important;
            }

            .text-base {
                font-size: 9px !important;
            }

            .bg-blue-50 .text-2xl,
            .bg-green-50 .text-2xl,
            .bg-purple-50 .text-2xl,
            .bg-yellow-50 .text-2xl {
                font-size: 11px !important;
            }

            .text-gray-700 {
                font-size: 8px !important;
                line-height: 1.3 !important;
                -webkit-line-clamp: 10 !important;
            }

            /* 背景とボーダーを削除 */
            * {
                background: white !important;
                box-shadow: none !important;
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
                break-inside: avoid !important;
            }

            /* 余白調整 */
            .max-w-7xl,
            .mx-auto,
            .px-6,
            .py-12 {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .mb-6,
            .p-6,
            .gap-6 {
                margin-bottom: 2px !important;
                padding: 2px !important;
                gap: 2px !important;
            }

            h2,
            h3,
            h4 {
                font-size: 10px !important;
                margin: 2px 0 !important;
            }

            /* 印刷専用タイトル */
            .print-only {
                display: block !important;
            }
        }

        /* 通常時は印刷専用要素を隠す */
        .print-only {
            display: none;
        }
    </style>

    <!-- 印刷用JavaScript -->
    <script>
        function printReport() {
            console.log('Print report triggered');
            if (window.moodSleepChart) {
                console.log('Chart exists, preparing for print');
                window.moodSleepChart.resize();
                window.moodSleepChart.update('none'); // アニメーションなしで更新
                setTimeout(() => {
                    console.log('Opening print dialog');
                    window.print();
                }, 800); // 待機時間を延長
            } else {
                console.log('No chart found, printing directly');
                window.print();
            }
        }
    </script>
</div>
