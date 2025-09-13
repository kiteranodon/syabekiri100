<?php

use function Livewire\Volt\{computed};
use App\Models\SleepLog;
use App\Models\DailyLog;

// 初回記録日以降の全日付を取得（記録がない日も含む）
$allDatesWithLogs = computed(function () {
    // 初回記録日を取得
    $firstLogDate = DailyLog::where('user_id', auth()->id())
        ->orderBy('date', 'asc')
        ->value('date');

    if (!$firstLogDate) {
        return collect();
    }

    // 今日までの全日付を生成
    $startDate = \Carbon\Carbon::parse($firstLogDate);
    $endDate = \Carbon\Carbon::now();
    $allDates = [];

    $currentDate = $startDate->copy();
    while ($currentDate->lte($endDate)) {
        $allDates[] = $currentDate->toDateString();
        $currentDate->addDay();
    }

    // 各日付の睡眠記録を取得
    $sleepLogsByDate = SleepLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })
        ->with('dailyLog')
        ->get()
        ->groupBy(fn($log) => $log->dailyLog->date->toDateString());

    // 全日付と記録をマージ（新しい日付から表示）
    $result = [];
    foreach (array_reverse($allDates) as $date) {
        $sleepLog = $sleepLogsByDate->get($date)?->first();
        $result[$date] = $sleepLog;
    }

    return collect($result);
});

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('睡眠ログ') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('sleep-logs.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    新規作成
                </a>
                <a href="{{ route('sleep-logs.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3a1 1 0 011-1h6a1 1 0 011 1v4h3a1 1 0 110 2h-1v9a2 2 0 01-2 2H7a2 2 0 01-2-2V9H4a1 1 0 110-2h4z" />
                    </svg>
                    過去の記録
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">睡眠記録履歴</h3>
                        <p class="text-sm text-gray-500">
                            {{ $this->allDatesWithLogs->count() }}日分の期間
                        </p>
                    </div>

                    @if ($this->allDatesWithLogs->count() > 0)
                        <div class="space-y-4">
                            @foreach ($this->allDatesWithLogs as $date => $sleepLog)
                                <div class="border border-gray-200 rounded-lg p-6">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-4 mb-4">
                                                <h4 class="text-lg font-medium text-gray-900">
                                                    {{ \Carbon\Carbon::parse($date)->format('Y年m月d日 (D)') }}
                                                </h4>

                                                @if ($sleepLog && $sleepLog->sleep_hours)
                                                    <span
                                                        class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                                        @if ($sleepLog->sleep_hours < 6) bg-red-100 text-red-800
                                                        @elseif($sleepLog->sleep_hours < 8) bg-yellow-100 text-yellow-800
                                                        @else bg-green-100 text-green-800 @endif
                                                    ">
                                                        {{ $sleepLog->sleep_duration_formatted }}睡眠
                                                    </span>
                                                @endif
                                            </div>

                                            @if ($sleepLog)
                                                <!-- 記録がある場合 -->
                                                <div
                                                    class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm text-gray-600 mb-4">
                                                    @if ($sleepLog->bedtime)
                                                        <div class="flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                                            </svg>
                                                            <span class="font-medium">就寝:</span>
                                                            <span
                                                                class="ml-1">{{ \Carbon\Carbon::parse($sleepLog->bedtime)->format('H:i') }}</span>
                                                        </div>
                                                    @endif

                                                    @if ($sleepLog->wakeup_time)
                                                        <div class="flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707" />
                                                            </svg>
                                                            <span class="font-medium">起床:</span>
                                                            <span
                                                                class="ml-1">{{ \Carbon\Carbon::parse($sleepLog->wakeup_time)->format('H:i') }}</span>
                                                        </div>
                                                    @endif

                                                    @if ($sleepLog->sleep_quality)
                                                        <div class="flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                                                            </svg>
                                                            <span class="font-medium">質:</span>
                                                            <span class="ml-1">{{ $sleepLog->sleep_quality }}/5</span>
                                                        </div>
                                                    @endif

                                                    @if ($sleepLog->sleep_hours)
                                                        <div class="flex items-center">
                                                            <svg class="w-4 h-4 mr-2" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                            </svg>
                                                            <span class="font-medium">時間:</span>
                                                            <span
                                                                class="ml-1">{{ $sleepLog->sleep_duration_formatted }}</span>
                                                        </div>
                                                    @endif
                                                </div>

                                                <!-- 編集ボタン -->
                                                <div class="flex justify-end">
                                                    <a href="{{ route('sleep-logs.edit', $sleepLog->id) }}"
                                                        class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                        編集
                                                    </a>
                                                </div>
                                            @else
                                                <!-- 記録がない場合 -->
                                                <div class="text-center py-8 bg-gray-50 rounded-lg">
                                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                                    </svg>
                                                    <h4 class="mt-2 text-lg font-medium text-gray-900">記録なし</h4>
                                                    <p class="mt-1 text-sm text-gray-500">この日の睡眠記録はありません</p>
                                                    <div class="mt-4">
                                                        <a href="{{ route('sleep-logs.create') }}?date={{ $date }}"
                                                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                                            <svg class="w-4 h-4 mr-2" fill="none"
                                                                stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                            </svg>
                                                            記録を追加
                                                        </a>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-16">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">睡眠記録がありません</h3>
                            <p class="mt-2 text-gray-500">最初の睡眠記録を作成してみましょう。</p>
                            <div class="mt-8">
                                <a href="{{ route('sleep-logs.create') }}"
                                    class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-green-600 hover:bg-green-700">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    新規作成
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
