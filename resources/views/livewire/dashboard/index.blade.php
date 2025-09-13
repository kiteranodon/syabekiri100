<?php

use function Livewire\Volt\{state, computed};
use App\Models\DailyLog;
use App\Models\SleepLog;
use App\Models\MedicationLog;

state(['selectedDate' => now()->toDateString()]);

$todayLog = computed(function () {
    return DailyLog::where('user_id', auth()->id())
        ->where('date', $this->selectedDate)
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

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('ホーム') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                                        <p class="text-2xl font-semibold text-purple-600">
                                            {{ $this->todayLog->medicationLogs->where('taken', true)->count() }}/{{ $this->todayLog->medicationLogs->count() }}
                                        </p>
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

            <!-- クイックアクション -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">クイックアクション</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <a href="{{ route('daily-logs.create') }}"
                            class="flex items-center p-4 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors">
                            <svg class="h-6 w-6 text-blue-600 mr-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            <span class="text-sm font-medium text-blue-900">気分記録</span>
                        </a>

                        <a href="{{ route('sleep-logs.create') }}"
                            class="flex items-center p-4 bg-green-50 rounded-lg hover:bg-green-100 transition-colors">
                            <svg class="h-6 w-6 text-green-600 mr-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <span class="text-sm font-medium text-green-900">睡眠記録</span>
                        </a>

                        <a href="{{ route('medications.index') }}"
                            class="flex items-center p-4 bg-purple-50 rounded-lg hover:bg-purple-100 transition-colors">
                            <svg class="h-6 w-6 text-purple-600 mr-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <span class="text-sm font-medium text-purple-900">服薬管理</span>
                        </a>

                        <a href="{{ route('reports.create') }}"
                            class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors">
                            <svg class="h-6 w-6 text-orange-600 mr-3" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-sm font-medium text-orange-900">レポート作成</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
