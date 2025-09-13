<?php

use function Livewire\Volt\{computed};
use App\Models\MedicationLog;

$medicationLogs = computed(function () {
    return MedicationLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })
        ->with('dailyLog')
        ->orderBy('created_at', 'desc')
        ->paginate(20);
});

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('服用履歴') }}</h2>
            <div class="flex space-x-3">
                <a href="{{ route('medications.index') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    戻る
                </a>
                <a href="{{ route('medications.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    新規作成
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- 服薬記録履歴 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">服薬記録履歴</h3>
                        <p class="text-sm text-gray-500">
                            全{{ $this->medicationLogs->total() }}件の記録
                        </p>
                    </div>

                    @if ($this->medicationLogs->count() > 0)
                        <div class="space-y-6">
                            @foreach ($this->medicationLogs->groupBy(fn($log) => $log->dailyLog->date->format('Y-m-d')) as $date => $logs)
                                <div class="border border-gray-200 rounded-lg p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-lg font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($date)->format('Y年m月d日 (D)') }}
                                        </h4>
                                        @php
                                            $totalCount = $logs->count();
                                            $takenCount = $logs->where('taken', true)->count();
                                            $adherenceRate =
                                                $totalCount > 0 ? round(($takenCount / $totalCount) * 100, 1) : 0;
                                        @endphp
                                        <div class="flex items-center space-x-4">
                                            <span class="text-sm text-gray-600">
                                                {{ $takenCount }}/{{ $totalCount }} 服薬
                                            </span>
                                            <span
                                                class="px-3 py-1 text-sm font-medium rounded-full
                                                @if ($adherenceRate >= 80) bg-green-100 text-green-800
                                                @elseif($adherenceRate >= 60) bg-yellow-100 text-yellow-800
                                                @else bg-red-100 text-red-800 @endif
                                            ">
                                                {{ $adherenceRate }}%
                                            </span>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach ($logs as $log)
                                            <div
                                                class="flex items-center justify-between p-4 
                                                {{ $log->taken ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }} 
                                                rounded-lg">
                                                <div class="flex items-center">
                                                    @if ($log->taken)
                                                        <svg class="w-5 h-5 text-green-600 mr-3" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5 text-red-600 mr-3" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                        </svg>
                                                    @endif
                                                    <div>
                                                        <span
                                                            class="font-medium {{ $log->taken ? 'text-green-800' : 'text-red-800' }}">
                                                            {{ $log->medicine_name }}
                                                        </span>
                                                        @if ($log->timing)
                                                            <div class="mt-1">
                                                                <span
                                                                    class="px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded">
                                                                    {{ $log->timing_display }}
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>

                                                <span
                                                    class="text-xs font-medium {{ $log->taken ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $log->taken ? '服薬済み' : '未服薬' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-8">
                            {{ $this->medicationLogs->links() }}
                        </div>
                    @else
                        <div class="text-center py-16">
                            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <h3 class="mt-4 text-lg font-medium text-gray-900">服薬記録がありません</h3>
                            <p class="mt-2 text-gray-500">最初の服薬記録を作成してみましょう。</p>
                            <div class="mt-8">
                                <a href="{{ route('medications.create') }}"
                                    class="inline-flex items-center px-6 py-3 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
