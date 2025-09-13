<?php

use function Livewire\Volt\{state, computed};
use App\Models\MedicationLog;
use App\Models\DailyLog;

state([
    'showAddModal' => false,
    'showDeleteModal' => false,
    'newMedicineName' => '',
    'newMedicineTiming' => '',
    'selectedMedicationId' => null,
]);

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

// 薬の追加
$addMedication = function () {
    if (empty($this->newMedicineName) || empty($this->newMedicineTiming)) {
        session()->flash('error', '薬名とタイミングを入力してください。');
        return;
    }

    // 今日の日次ログを取得または作成
    $dailyLog = DailyLog::firstOrCreate([
        'user_id' => auth()->id(),
        'date' => now()->toDateString(),
    ]);

    // 薬を追加
    MedicationLog::create([
        'daily_log_id' => $dailyLog->id,
        'medicine_name' => $this->newMedicineName,
        'timing' => $this->newMedicineTiming,
        'taken' => false,
    ]);

    // フォームをリセット
    $this->newMedicineName = '';
    $this->newMedicineTiming = '';
    $this->showAddModal = false;

    session()->flash('success', '薬を追加しました。');
};

// 薬の削除
$deleteMedication = function () {
    if (!$this->selectedMedicationId) {
        session()->flash('error', '削除する薬を選択してください。');
        return;
    }

    MedicationLog::where('id', $this->selectedMedicationId)
        ->where('daily_log_id', function ($query) {
            $query
                ->select('id')
                ->from('daily_logs')
                ->where('user_id', auth()->id())
                ->where('date', now()->toDateString())
                ->limit(1);
        })
        ->delete();

    $this->selectedMedicationId = null;
    $this->showDeleteModal = false;

    session()->flash('success', '薬を削除しました。');
};

// 時間帯別一括服薬
$takeAllMedicationsInTiming = function ($timing) {
    $todayDailyLog = DailyLog::where('user_id', auth()->id())
        ->where('date', now()->toDateString())
        ->first();

    if (!$todayDailyLog) {
        session()->flash('error', '今日の記録が見つかりません。');
        return;
    }

    $updatedCount = MedicationLog::where('daily_log_id', $todayDailyLog->id)
        ->where('timing', $timing)
        ->update(['taken' => true]);

    if ($updatedCount > 0) {
        session()->flash('success', $updatedCount . '種類の薬を服薬済みにしました。');
    } else {
        session()->flash('info', 'この時間帯に薬がありません。');
    }
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
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">今日の服薬状況</h3>
                        <div class="flex justify-between items-center">
                            <p class="text-sm text-gray-600">{{ now()->format('Y年m月d日 (D)') }}</p>
                            <div class="flex items-center space-x-3">
                                <button wire:click="$set('showAddModal', true)"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-100 rounded-md hover:bg-blue-200 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    薬の追加
                                </button>
                                <button wire:click="$set('showDeleteModal', true)"
                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-red-700 bg-red-100 rounded-md hover:bg-red-200 transition-colors">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                    薬の削除
                                </button>
                                <a href="{{ route('medications.history') }}"
                                    class="text-sm text-purple-600 hover:text-purple-800">
                                    服用履歴 →
                                </a>
                            </div>
                        </div>
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
                                        <div class="flex justify-between items-center mb-4">
                                            <h4 class="text-lg font-medium text-gray-900 flex items-center">
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

                                            @if ($timingKey !== 'as_needed' && $group['medications']->count() > 0)
                                                <button wire:click="takeAllMedicationsInTiming('{{ $timingKey }}')"
                                                    class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-green-700 bg-green-100 rounded-md hover:bg-green-200 transition-colors">
                                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M5 13l4 4L19 7" />
                                                    </svg>
                                                    すべて飲んだ
                                                </button>
                                            @endif
                                        </div>

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

        </div>
    </div>

    <!-- 薬の追加モーダル -->
    @if ($showAddModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">薬の追加</h3>

                <div class="space-y-4">
                    <!-- 薬名入力 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">薬名</label>
                        <input type="text" wire:model="newMedicineName" placeholder="薬名を入力してください"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                    </div>

                    <!-- タイミング選択 -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">タイミング</label>
                        <select wire:model="newMedicineTiming"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            <option value="">タイミングを選択</option>
                            <option value="morning">朝</option>
                            <option value="afternoon">昼</option>
                            <option value="evening">晩</option>
                            <option value="bedtime">就寝前</option>
                            <option value="as_needed">頓服</option>
                        </select>
                    </div>
                </div>

                <!-- ボタン -->
                <div class="mt-6 flex justify-end space-x-3">
                    <button wire:click="$set('showAddModal', false)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        キャンセル
                    </button>
                    <button wire:click="addMedication"
                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700">
                        追加
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- 薬の削除モーダル -->
    @if ($showDeleteModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">薬の削除</h3>

                @if ($this->todayMedications->count() > 0)
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">削除する薬を選択してください：</p>

                        <!-- 薬選択 -->
                        <div class="space-y-2">
                            @foreach ($this->todayMedications as $medication)
                                <label
                                    class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="radio" wire:model="selectedMedicationId"
                                        value="{{ $medication->id }}"
                                        class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300">
                                    <div class="ml-3">
                                        <span
                                            class="font-medium text-gray-900">{{ $medication->medicine_name }}</span>
                                        <span
                                            class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded">
                                            {{ $medication->timing_display }}
                                        </span>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-sm text-gray-600">今日の薬がありません。</p>
                @endif

                <!-- ボタン -->
                <div class="mt-6 flex justify-end space-x-3">
                    <button wire:click="$set('showDeleteModal', false)"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        キャンセル
                    </button>
                    @if ($this->todayMedications->count() > 0)
                        <button wire:click="deleteMedication"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-700">
                            削除
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
