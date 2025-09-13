<?php

use function Livewire\Volt\{state, rules, computed};
use App\Models\DailyLog;
use App\Models\MedicationLog;

state([
    'selected_daily_log_id' => null,
    'create_new_date' => null,
    'medications' => [], // 薬名とタイミングの組み合わせを保存
]);

rules([
    'selected_daily_log_id' => 'required_without:create_new_date|exists:daily_logs,id',
    'create_new_date' => 'required_without:selected_daily_log_id|date|before_or_equal:today',
    'medications' => 'required|array|min:1',
    'medications.*.medicine_name' => 'required|string|max:255',
    'medications.*.timing' => 'required|string|in:morning,afternoon,evening,bedtime,as_needed',
    'medications.*.taken' => 'boolean',
]);

$availableDailyLogs = computed(function () {
    return DailyLog::where('user_id', auth()->id())
        ->where('date', '>=', now()->subDays(90)->toDateString()) // 過去90日まで
        ->orderBy('date', 'desc')
        ->get();
});

$addMedicationTiming = function ($medicineName, $timing) {
    $this->medications[] = [
        'medicine_name' => $medicineName,
        'timing' => $timing,
        'taken' => false,
    ];
};

$removeMedication = function ($index) {
    unset($this->medications[$index]);
    $this->medications = array_values($this->medications);
};

$save = function () {
    $this->validate();

    $dailyLogId = $this->selected_daily_log_id;

    // 新しい日付が指定された場合は日次ログを作成
    if ($this->create_new_date) {
        $dailyLog = DailyLog::firstOrCreate([
            'user_id' => auth()->id(),
            'date' => $this->create_new_date,
        ]);
        $dailyLogId = $dailyLog->id;
    }

    foreach ($this->medications as $medication) {
        if (!empty($medication['medicine_name']) && !empty($medication['timing'])) {
            MedicationLog::create([
                'daily_log_id' => $dailyLogId,
                'medicine_name' => $medication['medicine_name'],
                'timing' => $medication['timing'],
                'taken' => $medication['taken'],
            ]);
        }
    }

    session()->flash('success', '服薬ログを保存しました。');
    return redirect()->route('medications.index');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('服薬記録作成') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="save" class="space-y-8">
                        <!-- 記録日選択 -->
                        <div class="space-y-4">
                            <label class="block text-sm font-medium text-gray-700">
                                記録日の選択方法
                            </label>

                            <!-- 既存の日次ログから選択 -->
                            <div>
                                <label for="selected_daily_log_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    既存の日次ログから選択
                                </label>
                                <select wire:model="selected_daily_log_id" id="selected_daily_log_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                    <option value="">既存の日次ログを選択してください</option>
                                    @foreach ($this->availableDailyLogs as $dailyLog)
                                        <option value="{{ $dailyLog->id }}">
                                            {{ $dailyLog->date->format('Y年m月d日 (D)') }}
                                            @if ($dailyLog->mood_score)
                                                - 気分: <x-mood-icon :score="$dailyLog->mood_score"
                                                    size="sm" />{{ $dailyLog->mood_score }}/5
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                                @error('selected_daily_log_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- または新しい日付を指定 -->
                            <div class="border-t pt-4">
                                <label for="create_new_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    または新しい日付を指定（過去の日付も可能）
                                </label>
                                <input type="date" wire:model="create_new_date" id="create_new_date"
                                    max="{{ now()->toDateString() }}"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                @error('create_new_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">
                                    新しい日付を指定した場合、日次ログも自動で作成されます
                                </p>
                            </div>
                        </div>

                        <!-- 薬の追加セクション -->
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 mb-4">薬の追加</h3>
                            <p class="text-sm text-gray-600 mb-6">
                                下のフォームで薬名とタイミングを指定してください。
                                同じ薬でも時間帯ごとに独立して管理されます。
                            </p>


                            <!-- 薬追加フォーム -->
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-3">薬の追加</h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                    <input type="text" wire:model.defer="customMedicineName" placeholder="薬名を入力"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">

                                    <select wire:model.defer="customTiming"
                                        class="rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                        <option value="">タイミングを選択</option>
                                        <option value="morning">朝</option>
                                        <option value="afternoon">昼</option>
                                        <option value="evening">晩</option>
                                        <option value="bedtime">就寝前</option>
                                        <option value="as_needed">頓服</option>
                                    </select>

                                    <button type="button"
                                        wire:click="addMedicationTiming($wire.customMedicineName, $wire.customTiming)"
                                        class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition-colors">
                                        追加
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- 追加された服薬記録 -->
                        @if (count($medications) > 0)
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">追加された服薬記録</h3>
                                <div class="space-y-3">
                                    @foreach ($medications as $index => $medication)
                                        <div
                                            class="flex items-center justify-between p-4 border border-gray-200 rounded-lg bg-gray-50">
                                            <div class="flex items-center space-x-4">
                                                <div>
                                                    <span
                                                        class="font-medium text-gray-900">{{ $medication['medicine_name'] }}</span>
                                                    <span
                                                        class="ml-2 px-2 py-1 text-xs font-medium bg-blue-100 text-blue-700 rounded">
                                                        {{ $timingLabels[$medication['timing']] ?? $medication['timing'] }}
                                                    </span>
                                                </div>

                                                <div class="flex items-center">
                                                    <input type="checkbox"
                                                        wire:model="medications.{{ $index }}.taken"
                                                        class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                                                    <label class="ml-2 text-sm text-gray-700">
                                                        服薬済み
                                                    </label>
                                                </div>
                                            </div>

                                            <button type="button" wire:click="removeMedication({{ $index }})"
                                                class="text-red-600 hover:text-red-800 p-1">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 rounded-lg">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                </svg>
                                <h3 class="mt-2 text-sm font-medium text-gray-900">服薬記録がありません</h3>
                                <p class="mt-1 text-sm text-gray-500">上記から薬を追加してください。</p>
                            </div>
                        @endif

                        <!-- ボタン -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('medications.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                キャンセル
                            </a>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md shadow-sm hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
