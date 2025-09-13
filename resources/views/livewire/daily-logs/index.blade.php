<?php

use function Livewire\Volt\{computed};
use App\Models\DailyLog;

$logs = computed(function () {
    return DailyLog::where('user_id', auth()->id())
        ->with(['sleepLog', 'medicationLogs'])
        ->orderBy('date', 'desc')
        ->paginate(15);
});

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('日次ログ') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('daily-logs.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    新規作成
                </a>
                <a href="{{ route('daily-logs.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
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
                    @if ($this->logs->count() > 0)
                        <div class="space-y-4">
                            @foreach ($this->logs as $log)
                                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50">
                                    <div class="flex justify-between items-start">
                                        <div class="flex-1">
                                            <div class="flex items-center space-x-4 mb-2">
                                                <h3 class="text-lg font-medium text-gray-900">
                                                    {{ $log->date->format('Y年m月d日 (D)') }}
                                                </h3>
                                                @if ($log->mood_score)
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                                        @if ($log->mood_score <= 2) bg-red-100 text-red-800
                                                        @elseif($log->mood_score == 3) bg-yellow-100 text-yellow-800
                                                        @else bg-green-100 text-green-800 @endif
                                                    ">
                                                        気分: {{ $log->mood_score }}/5
                                                    </span>
                                                @endif
                                            </div>

                                            @if ($log->free_note)
                                                <p class="text-gray-700 mb-3">{{ $log->free_note }}</p>
                                            @endif

                                            <div class="flex items-center space-x-6 text-sm text-gray-500">
                                                @if ($log->sleepLog)
                                                    <span class="flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                                                        </svg>
                                                        睡眠: {{ $log->sleepLog->sleep_hours }}h
                                                    </span>
                                                @endif

                                                @if ($log->medicationLogs->count() > 0)
                                                    <span class="flex items-center">
                                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                                        </svg>
                                                        服薬:
                                                        {{ $log->medicationLogs->where('taken', true)->count() }}/{{ $log->medicationLogs->count() }}
                                                    </span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="flex space-x-2">
                                            <a href="{{ route('daily-logs.edit', $log->date->format('Y-m-d')) }}"
                                                class="text-blue-600 hover:text-blue-800">
                                                編集
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $this->logs->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">日次ログがありません</h3>
                            <p class="mt-1 text-sm text-gray-500">最初の日次ログを作成してみましょう。</p>
                            <div class="mt-6">
                                <a href="{{ route('daily-logs.create') }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
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
