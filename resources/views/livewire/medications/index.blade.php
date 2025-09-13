<?php

use function Livewire\Volt\{computed};
use App\Models\MedicationLog;
use App\Models\DailyLog;

$medicationLogs = computed(function () {
    return MedicationLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })
        ->with('dailyLog')
        ->orderBy('created_at', 'desc')
        ->paginate(20);
});

$todayMedications = computed(function () {
    $todayDailyLog = DailyLog::where('user_id', auth()->id())
        ->where('date', now()->toDateString())
        ->with('medicationLogs')
        ->first();

    return $todayDailyLog ? $todayDailyLog->medicationLogs : collect();
});

$medicationStats = computed(function () {
    $logs = MedicationLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })->get();

    // 定時薬のみで統計を計算（回数ベース）
    $totalScheduledDoses = 0;
    $totalTakenDoses = 0;

    foreach ($logs as $log) {
        if ($log->is_regular_medication) {
            $totalScheduledDoses += $log->scheduled_doses_count;
            $totalTakenDoses += $log->taken_doses_count;
        }
    }

    $adherenceRate = $totalScheduledDoses > 0 ? ($totalTakenDoses / $totalScheduledDoses) * 100 : 0;

    // 薬別の統計も回数ベースで計算
    $medicineNames = $logs
        ->filter(fn($log) => $log->is_regular_medication)
        ->groupBy('medicine_name')
        ->map(function ($group) {
            $totalScheduled = $group->sum('scheduled_doses_count');
            $totalTaken = $group->sum('taken_doses_count');

            return [
                'name' => $group->first()->medicine_name,
                'total_scheduled' => $totalScheduled,
                'total_taken' => $totalTaken,
                'rate' => $totalScheduled > 0 ? ($totalTaken / $totalScheduled) * 100 : 0,
            ];
        });

    return [
        'total_scheduled_doses' => $totalScheduledDoses,
        'total_taken_doses' => $totalTakenDoses,
        'adherence_rate' => round($adherenceRate, 1),
        'medicine_breakdown' => $medicineNames,
    ];
});

$updateMedicationStatus = function ($medicationId, $taken) {
    MedicationLog::where('id', $medicationId)
        ->where('daily_log_id', function ($query) {
            $query
                ->select('id')
                ->from('daily_logs')
                ->where('user_id', auth()->id())
                ->where('date', now()->toDateString())
                ->limit(1);
        })
        ->update(['taken' => $taken]);
};

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('服薬管理') }}
            </h2>
            <div class="flex space-x-3">
                <a href="{{ route('medications.today') }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    今日の服薬
                </a>
                <a href="{{ route('medications.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    新規作成
                </a>
                <a href="{{ route('medications.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-orange-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-orange-700">
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
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- 今日の服薬状況 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900">今日の服薬状況 - {{ now()->format('Y年m月d日 (D)') }}</h3>
                        <a href="{{ route('medications.today') }}" class="text-sm text-green-600 hover:text-green-800">
                            編集する →
                        </a>
                    </div>

                    @if ($this->todayMedications->count() > 0)
                        @php
                            $timingGroups = [
                                'morning' => ['label' => '朝', 'icon' => '🌅', 'medications' => collect()],
                                'afternoon' => ['label' => '昼', 'icon' => '☀️', 'medications' => collect()],
                                'evening' => ['label' => '夜', 'icon' => '🌙', 'medications' => collect()],
                                'bedtime' => ['label' => '就寝前', 'icon' => '😴', 'medications' => collect()],
                                'as_needed' => ['label' => '頓服', 'icon' => '💊', 'medications' => collect()],
                            ];

                            foreach ($this->todayMedications as $medication) {
                                $timing = $medication->timing ?? 'morning';
                                if (isset($timingGroups[$timing])) {
                                    $timingGroups[$timing]['medications']->push($medication);
                                }
                            }

                            // 定時薬での服薬遵守率計算（回数ベース）
                            $totalScheduledDoses = 0;
                            $totalTakenDoses = 0;

                            foreach ($this->todayMedications as $med) {
                                if ($med->is_regular_medication) {
                                    $totalScheduledDoses += $med->scheduled_doses_count;
                                    $totalTakenDoses += $med->taken_doses_count;
                                }
                            }

                            $regularAdherenceRate =
                                $totalScheduledDoses > 0
                                    ? round(($totalTakenDoses / $totalScheduledDoses) * 100, 1)
                                    : 0;
                        @endphp

                        <div class="space-y-6">
                            @foreach ($timingGroups as $timingKey => $group)
                                @if ($group['medications']->count() > 0)
                                    <div
                                        class="border border-gray-200 rounded-lg p-4 
                                        {{ $timingKey === 'as_needed' ? 'bg-yellow-50' : 'bg-gray-50' }}">
                                        <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                            <span class="mr-2">{{ $group['icon'] }}</span>
                                            {{ $group['label'] }}
                                            <span class="ml-2 text-sm text-gray-500">
                                                ({{ $group['medications']->count() }}種類)
                                            </span>
                                            @if ($timingKey === 'as_needed')
                                                <span
                                                    class="ml-2 text-xs text-yellow-700 bg-yellow-200 px-2 py-1 rounded">
                                                    遵守率対象外
                                                </span>
                                            @endif
                                        </h4>

                                        <div
                                            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                                            @foreach ($group['medications'] as $medication)
                                                <div
                                                    class="p-3 border border-gray-200 rounded-md bg-white
                                                    {{ $medication->taken ? 'border-green-300 bg-green-50' : '' }}">

                                                    <!-- 薬の名前 -->
                                                    <div class="mb-3">
                                                        <h5 class="font-medium text-gray-900 text-sm">
                                                            {{ $medication->medicine_name }}
                                                        </h5>
                                                    </div>

                                                    <!-- Yes/No タブ -->
                                                    <div
                                                        class="flex rounded-md overflow-hidden border border-gray-300 mb-2">
                                                        <button
                                                            wire:click="updateMedicationStatus({{ $medication->id }}, false)"
                                                            class="flex-1 px-2 py-2 text-xs font-medium transition-colors
                                                                {{ !$medication->taken ? 'bg-red-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                                                            No
                                                        </button>
                                                        <button
                                                            wire:click="updateMedicationStatus({{ $medication->id }}, true)"
                                                            class="flex-1 px-2 py-2 text-xs font-medium transition-colors border-l border-gray-300
                                                                {{ $medication->taken ? 'bg-green-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                                                            Yes
                                                        </button>
                                                    </div>

                                                    <!-- 状態表示 -->
                                                    <div class="text-center">
                                                        <span
                                                            class="text-xs font-medium {{ $medication->taken ? 'text-green-600' : 'text-red-600' }}">
                                                            {{ $medication->taken ? '済' : '未' }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>

                        <!-- 服薬遵守率（頓服除く） -->
                        <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="text-sm font-medium text-blue-900">定時薬服薬遵守率</span>
                                    <p class="text-xs text-blue-700 mt-1">※頓服薬は除外して計算</p>
                                </div>
                                <div class="text-right">
                                    <span class="text-xl font-bold text-blue-900">{{ $regularAdherenceRate }}%</span>
                                    <p class="text-xs text-blue-700">
                                        {{ $totalTakenDoses }} / {{ $totalScheduledDoses }} 回
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <h4 class="mt-2 text-sm font-medium text-gray-900">今日の服薬記録がありません</h4>
                            <p class="mt-1 text-sm text-gray-500">「今日の服薬」ボタンから記録を開始してください。</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- 服薬統計 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">服薬統計（頓服薬除く）</h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="text-center">
                            <p class="text-3xl font-semibold text-purple-600">
                                {{ $this->medicationStats['adherence_rate'] }}%</p>
                            <p class="text-sm text-gray-500">服薬遵守率</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-semibold text-blue-600">
                                {{ $this->medicationStats['total_taken_doses'] }}</p>
                            <p class="text-sm text-gray-500">服薬済み回数</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-semibold text-gray-600">
                                {{ $this->medicationStats['total_scheduled_doses'] }}</p>
                            <p class="text-sm text-gray-500">予定服薬回数</p>
                        </div>
                    </div>

                    @if ($this->medicationStats['medicine_breakdown']->count() > 0)
                        <div class="space-y-3">
                            <h4 class="font-medium text-gray-900">薬別服薬率</h4>
                            @foreach ($this->medicationStats['medicine_breakdown'] as $medicine)
                                <div class="flex items-center justify-between p-3 bg-gray-50 rounded">
                                    <span class="font-medium text-gray-900">{{ $medicine['name'] }}</span>
                                    <div class="flex items-center space-x-2">
                                        <span
                                            class="text-sm text-gray-600">{{ $medicine['total_taken'] }}/{{ $medicine['total_scheduled'] }}
                                            回</span>
                                        <span
                                            class="px-2 py-1 text-xs font-medium rounded-full 
                                            @if ($medicine['rate'] >= 80) bg-green-100 text-green-800
                                            @elseif($medicine['rate'] >= 60) bg-yellow-100 text-yellow-800
                                            @else bg-red-100 text-red-800 @endif
                                        ">
                                            {{ round($medicine['rate'], 1) }}%
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- 服薬記録一覧 -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">服薬記録履歴</h3>

                    @if ($this->medicationLogs->count() > 0)
                        <div class="space-y-4">
                            @foreach ($this->medicationLogs->groupBy(fn($log) => $log->dailyLog->date->format('Y-m-d')) as $date => $logs)
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <h4 class="text-lg font-medium text-gray-900 mb-3">
                                        {{ \Carbon\Carbon::parse($date)->format('Y年m月d日 (D)') }}
                                    </h4>

                                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                                        @foreach ($logs as $log)
                                            <div
                                                class="flex items-center justify-between p-3 
                                                {{ $log->taken ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }} 
                                                rounded">
                                                <div class="flex items-center">
                                                    @if ($log->taken)
                                                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7" />
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5 text-red-600 mr-2" fill="none"
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
                                                    class="text-xs {{ $log->taken ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $log->taken ? '服薬済み' : '未服薬' }}
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6">
                            {{ $this->medicationLogs->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">服薬記録がありません</h3>
                            <p class="mt-1 text-sm text-gray-500">最初の服薬記録を作成してみましょう。</p>
                            <div class="mt-6">
                                <a href="{{ route('medications.create') }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-purple-600 hover:bg-purple-700">
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
