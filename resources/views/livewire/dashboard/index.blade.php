<?php

use function Livewire\Volt\{state, computed, mount, on};
use App\Models\DailyLog;
use App\Models\SleepLog;
use App\Models\MedicationLog;

state(['selectedDate' => now()->toDateString()]);

mount(function () {
    // まとめて記入完了時のリフレッシュ
    if (session()->has('batch_completed')) {
        session()->forget('batch_completed');
    }
});

// データリフレッシュ機能
$refreshData = function () {
    // 選択日付を更新してcomputedプロパティを強制的に再計算
    $this->selectedDate = now()->toDateString();

    // 成功メッセージを表示
    session()->flash('success', 'データを更新しました。');
};

$todayLog = computed(function () {
    return DailyLog::where('user_id', auth()->id())
        ->where('date', $this->selectedDate) // selectedDateを使用してリアクティブに
        ->with(['sleepLog', 'medicationLogs'])
        ->first();
});

$recentLogs = computed(function () {
    return DailyLog::where('user_id', auth()->id())
        ->with(['sleepLog', 'medicationLogs'])
        ->orderBy('date', 'desc')
        ->take(7)
        ->get();
});

$weeklyStats = computed(function () {
    $logs = $this->recentLogs;

    return [
        'avg_mood' => $logs->whereNotNull('mood_score')->avg('mood_score'),
        'avg_sleep' => $logs->map(fn($log) => $log->sleepLog?->sleep_hours)->filter()->avg(),
        'medication_adherence' => ($logs->flatMap(fn($log) => $log->medicationLogs)->where('taken', true)->count() / max($logs->flatMap(fn($log) => $log->medicationLogs)->count(), 1)) * 100,
        'total_entries' => $logs->count(),
    ];
});

// 平均睡眠時間を「○○時間○○分」形式で取得
$avgSleepFormatted = computed(function () {
    $avgSleep = $this->weeklyStats['avg_sleep'];
    if (!$avgSleep) {
        return 'なし';
    }

    $totalMinutes = round($avgSleep * 60);
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;

    return $hours . '時間' . $minutes . '分';
});

// 今日の定時服薬遵守率を計算
$todayRegularAdherenceRate = computed(function () {
    if (!$this->todayLog || $this->todayLog->medicationLogs->isEmpty()) {
        return 0;
    }

    // 頓服薬を除外した定時薬のみを対象とする
    $regularMedications = $this->todayLog->medicationLogs->where('timing', '!=', 'as_needed');

    if ($regularMedications->isEmpty()) {
        return 0;
    }

    $totalRegular = $regularMedications->count();
    $takenRegular = $regularMedications->where('taken', true)->count();

    return $totalRegular > 0 ? round(($takenRegular / $totalRegular) * 100, 1) : 0;
});

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('ホーム') }}
            </h2>
            <button wire:click="refreshData"
                class="inline-flex items-center px-3 py-2 bg-gray-100 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-200 focus:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition ease-in-out duration-150">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                更新
            </button>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- クイックアクション -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6 bg-white">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">クイックアクション</h3>

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                        <!-- まとめて記入ボタン（大きめ） -->
                        <div class="lg:col-span-2">
                            <a href="{{ route('daily-logs.create', ['flow' => 'batch']) }}"
                                class="flex items-center justify-center p-6 bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-lg hover:from-indigo-600 hover:to-purple-700 transition-all duration-200 shadow-lg">
                                <svg class="h-6 w-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <div class="text-center">
                                    <div class="text-lg font-bold">まとめて記入</div>
                                    <div class="text-sm opacity-90">（気分・睡眠・服薬）</div>
                                </div>
                            </a>
                        </div>

                        <!-- 個別記録ボタン（小さめ） -->
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-1 lg:col-span-2 gap-4">
                            <a href="{{ route('daily-logs.create') }}"
                                class="flex items-center p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                                <svg class="h-5 w-5 text-blue-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.01M15 10h1.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span class="text-sm font-medium text-blue-900">気分記録</span>
                            </a>

                            <a href="{{ route('sleep-logs.create') }}"
                                class="flex items-center p-3 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                                <svg class="h-5 w-5 text-green-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                </svg>
                                <span class="text-sm font-medium text-green-900">睡眠記録</span>
                            </a>

                            <a href="{{ route('medications.today') }}"
                                class="flex items-center p-3 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                                <svg class="h-5 w-5 text-purple-600 mr-2" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <span class="text-sm font-medium text-purple-900">服薬管理</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 今日の状況 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">今日の記録状況</h3>

                    @if ($this->todayLog)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- 気分スコア -->
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M14.828 14.828a4 4 0 01-5.656 0M9 10h1.01M15 10h1.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-blue-900">気分スコア</p>
                                        <p class="text-2xl font-semibold text-blue-600 flex items-center">
                                            @if ($this->todayLog?->mood_score)
                                                <x-mood-icon :score="$this->todayLog->mood_score" size="2xl" />
                                                <span class="ml-2">{{ $this->todayLog->mood_score }}/5</span>
                                            @else
                                                なし
                                            @endif
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- 睡眠時間 -->
                            <div class="bg-green-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-green-900">睡眠時間</p>
                                        <p class="text-2xl font-semibold text-green-600">
                                            {{ $this->todayLog->sleepLog?->sleep_duration_formatted ?? 'なし' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- 服薬状況 -->
                            <div class="bg-purple-50 p-4 rounded-lg">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-purple-900">服薬状況</p>
                                        <div class="flex items-center space-x-3">
                                            <p class="text-2xl font-semibold text-purple-600">
                                                {{ $this->todayLog->medicationLogs->where('taken', true)->count() }}/{{ $this->todayLog->medicationLogs->count() }}
                                            </p>
                                            <div class="text-right">
                                                <p class="text-lg font-semibold text-purple-700">
                                                    {{ $this->todayRegularAdherenceRate }}%
                                                </p>
                                                <p class="text-xs text-purple-600">
                                                    定時服薬遵守率
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if ($this->todayLog->free_note)
                            <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                                <h4 class="font-medium text-gray-900 mb-2">今日の日記</h4>
                                <p class="text-gray-700">{{ $this->todayLog->free_note }}</p>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">今日の記録がありません</h3>
                            <p class="mt-1 text-sm text-gray-500">今日の気分や出来事を記録してみましょう。</p>
                            <div class="mt-6">
                                <a href="{{ route('daily-logs.create') }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                    今日の記録を追加
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 週間統計 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-8">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">過去7日間の統計</h3>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="text-center">
                            <p class="text-2xl font-semibold text-blue-600 flex items-center justify-center">
                                @if ($this->weeklyStats['avg_mood'])
                                    <x-mood-icon :score="round($this->weeklyStats['avg_mood'])" size="2xl" />
                                    <span class="ml-2">{{ number_format($this->weeklyStats['avg_mood'], 1) }}</span>
                                @else
                                    なし
                                @endif
                            </p>
                            <p class="text-sm text-gray-500">平均気分スコア</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-semibold text-green-600">
                                {{ $this->avgSleepFormatted }}</p>
                            <p class="text-sm text-gray-500">平均睡眠時間</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-semibold text-purple-600">
                                {{ number_format($this->weeklyStats['medication_adherence'], 1) }}%</p>
                            <p class="text-sm text-gray-500">服薬遵守率</p>
                        </div>
                        <div class="text-center">
                            <p class="text-2xl font-semibold text-gray-600">{{ $this->weeklyStats['total_entries'] }}
                            </p>
                            <p class="text-sm text-gray-500">記録日数</p>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
