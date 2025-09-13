<?php

use function Livewire\Volt\{state, computed};
use App\Models\MedicationLog;
use App\Models\DailyLog;

state([
    'showAddModal' => false,
    'showDeleteModal' => false,
    'selectedDate' => null,
    'newMedicineName' => '',
    'newMedicineTiming' => '',
    'selectedMedicationId' => null,
]);

$medicationLogs = computed(function () {
    return MedicationLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })
        ->with('dailyLog')
        ->orderBy('created_at', 'desc')
        ->paginate(20);
});

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

    // 各日付の服薬記録を取得
    $medicationsByDate = MedicationLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })
        ->with('dailyLog')
        ->get()
        ->groupBy(fn($log) => $log->dailyLog->date->toDateString());

    // 全日付と記録をマージ
    $result = [];
    foreach (array_reverse($allDates) as $date) {
        // 新しい日付から表示
        $result[$date] = $medicationsByDate->get($date, collect());
    }

    return collect($result);
});

// 薬の追加モーダルを開く
$openAddModal = function ($date) {
    $this->selectedDate = $date;
    $this->newMedicineName = '';
    $this->newMedicineTiming = '';
    $this->showAddModal = true;
};

// 薬の削除モーダルを開く
$openDeleteModal = function ($date) {
    $this->selectedDate = $date;
    $this->selectedMedicationId = null;
    $this->showDeleteModal = true;
};

// 薬を追加
$addMedication = function () {
    if (empty($this->newMedicineName) || empty($this->newMedicineTiming)) {
        session()->flash('error', '薬名とタイミングを入力してください。');
        return;
    }

    // 日次ログを取得または作成
    $dailyLog = DailyLog::firstOrCreate([
        'user_id' => auth()->id(),
        'date' => $this->selectedDate,
    ]);

    // 薬を追加
    MedicationLog::create([
        'daily_log_id' => $dailyLog->id,
        'medicine_name' => $this->newMedicineName,
        'timing' => $this->newMedicineTiming,
        'taken' => false,
    ]);

    $this->showAddModal = false;
    $this->selectedDate = null;
    $this->newMedicineName = '';
    $this->newMedicineTiming = '';

    session()->flash('success', '薬を追加しました。');
};

// 薬を削除
$deleteMedication = function () {
    if (!$this->selectedMedicationId) {
        session()->flash('error', '削除する薬を選択してください。');
        return;
    }

    MedicationLog::where('id', $this->selectedMedicationId)
        ->whereHas('dailyLog', function ($query) {
            $query->where('user_id', auth()->id());
        })
        ->delete();

    $this->showDeleteModal = false;
    $this->selectedDate = null;
    $this->selectedMedicationId = null;

    session()->flash('success', '薬を削除しました。');
};

// モーダルを閉じる
$closeModals = function () {
    $this->showAddModal = false;
    $this->showDeleteModal = false;
    $this->selectedDate = null;
    $this->newMedicineName = '';
    $this->newMedicineTiming = '';
    $this->selectedMedicationId = null;
};

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
                            {{ $this->allDatesWithLogs->count() }}日分の記録
                        </p>
                    </div>

                    @if ($this->allDatesWithLogs->count() > 0)
                        <div class="space-y-6">
                            @foreach ($this->allDatesWithLogs as $date => $logs)
                                <div class="border border-gray-200 rounded-lg p-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-lg font-medium text-gray-900">
                                            {{ \Carbon\Carbon::parse($date)->format('Y年m月d日 (D)') }}
                                        </h4>
                                        <div class="flex items-center space-x-3">
                                            @if ($logs->count() > 0)
                                                @php
                                                    $totalCount = $logs->count();
                                                    $takenCount = $logs->where('taken', true)->count();
                                                    $adherenceRate =
                                                        $totalCount > 0
                                                            ? round(($takenCount / $totalCount) * 100, 1)
                                                            : 0;
                                                @endphp
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
                                            @endif

                                            <!-- 薬の追加ボタン -->
                                            <button wire:click="openAddModal('{{ $date }}')"
                                                class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-700 bg-blue-100 rounded hover:bg-blue-200 transition-colors">
                                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                </svg>
                                                薬の追加
                                            </button>

                                            @if ($logs->count() > 0)
                                                <!-- 薬の削除ボタン -->
                                                <button wire:click="openDeleteModal('{{ $date }}')"
                                                    class="inline-flex items-center px-2 py-1 text-xs font-medium text-red-700 bg-red-100 rounded hover:bg-red-200 transition-colors">
                                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    薬の削除
                                                </button>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($logs->count() > 0)
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

                                                    <div class="flex items-center space-x-2">
                                                        <span
                                                            class="text-xs font-medium {{ $log->taken ? 'text-green-600' : 'text-red-600' }}">
                                                            {{ $log->taken ? '服薬済み' : '未服薬' }}
                                                        </span>
                                                        <a href="{{ route('medications.edit', $log->id) }}"
                                                            class="inline-flex items-center p-1 text-gray-400 hover:text-gray-600 transition-colors">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                                    stroke-width="2"
                                                                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                            </svg>
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="text-center py-8 bg-gray-50 rounded-lg">
                                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                            <h4 class="mt-2 text-lg font-medium text-gray-900">記録なし</h4>
                                            <p class="mt-1 text-sm text-gray-500">この日の服薬記録はありません</p>
                                            <div class="mt-4">
                                                <button wire:click="openAddModal('{{ $date }}')"
                                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                                    </svg>
                                                    薬を追加
                                                </button>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
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

    <!-- 薬の追加モーダル -->
    @if ($showAddModal)
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-medium text-gray-900 mb-4">薬の追加</h3>
                <p class="text-sm text-gray-600 mb-4">
                    {{ \Carbon\Carbon::parse($selectedDate)->format('Y年m月d日 (D)') }} の薬を追加
                </p>

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
                    <button wire:click="closeModals"
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
                <p class="text-sm text-gray-600 mb-4">
                    {{ \Carbon\Carbon::parse($selectedDate)->format('Y年m月d日 (D)') }} から削除する薬を選択
                </p>

                @php
                    $dateLogData = $this->allDatesWithLogs->get($selectedDate, collect());
                @endphp

                @if ($dateLogData->count() > 0)
                    <div class="space-y-2">
                        @foreach ($dateLogData as $medication)
                            <label
                                class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                <input type="radio" wire:model="selectedMedicationId"
                                    value="{{ $medication->id }}"
                                    class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300">
                                <div class="ml-3">
                                    <span class="font-medium text-gray-900">{{ $medication->medicine_name }}</span>
                                    <span
                                        class="ml-2 px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-700 rounded">
                                        {{ $medication->timing_display }}
                                    </span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-600">この日に削除できる薬がありません。</p>
                @endif

                <!-- ボタン -->
                <div class="mt-6 flex justify-end space-x-3">
                    <button wire:click="closeModals"
                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        キャンセル
                    </button>
                    @if ($dateLogData->count() > 0)
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
