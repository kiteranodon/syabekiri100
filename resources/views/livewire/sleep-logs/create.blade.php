<?php

use function Livewire\Volt\{state, rules, computed};
use App\Models\DailyLog;
use App\Models\SleepLog;

state([
    'selected_daily_log_id' => null,
    'create_new_date' => null,
    'bedtime' => null,
    'wakeup_time' => null,
    'sleep_hours' => null,
    'sleep_quality' => null,
]);

rules([
    'selected_daily_log_id' => 'required_without:create_new_date|exists:daily_logs,id',
    'create_new_date' => 'required_without:selected_daily_log_id|date|before_or_equal:today',
    'bedtime' => 'nullable|date_format:H:i',
    'wakeup_time' => 'nullable|date_format:H:i',
    'sleep_hours' => 'nullable|numeric|min:0|max:24',
    'sleep_quality' => 'nullable|integer|min:1|max:5',
]);

$availableDailyLogs = computed(function () {
    return DailyLog::where('user_id', auth()->id())
        ->whereDoesntHave('sleepLog')
        ->where('date', '>=', now()->subDays(90)->toDateString()) // 過去90日まで
        ->orderBy('date', 'desc')
        ->get();
});

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

    $sleepLog = SleepLog::create([
        'daily_log_id' => $dailyLogId,
        'bedtime' => $this->bedtime,
        'wakeup_time' => $this->wakeup_time,
        'sleep_hours' => $this->sleep_hours,
        'sleep_quality' => $this->sleep_quality,
    ]);

    session()->flash('success', '睡眠ログを保存しました。');
    return redirect()->route('sleep-logs.index');
};

// 睡眠時間を自動計算
$calculateSleepHours = function () {
    if ($this->bedtime && $this->wakeup_time) {
        $bedtime = \Carbon\Carbon::createFromFormat('H:i', $this->bedtime);
        $wakeupTime = \Carbon\Carbon::createFromFormat('H:i', $this->wakeup_time);

        // 翌日の起床時間の場合
        if ($wakeupTime->lt($bedtime)) {
            $wakeupTime->addDay();
        }

        $this->sleep_hours = round($bedtime->diffInMinutes($wakeupTime) / 60, 2);
    }
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('睡眠ログ作成') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="save" class="space-y-6">
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
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                    <option value="">既存の日次ログを選択してください</option>
                                    @foreach ($this->availableDailyLogs as $dailyLog)
                                        <option value="{{ $dailyLog->id }}">
                                            {{ $dailyLog->date->format('Y年m月d日 (D)') }}
                                            @if ($dailyLog->mood_score)
                                                - 気分: {{ $dailyLog->mood_score }}/5
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
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                @error('create_new_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">
                                    新しい日付を指定した場合、日次ログも自動で作成されます
                                </p>
                            </div>
                        </div>

                        <!-- 就寝時間 -->
                        <div>
                            <label for="bedtime" class="block text-sm font-medium text-gray-700">
                                就寝時間
                            </label>
                            <input type="time" wire:model="bedtime" wire:change="calculateSleepHours" id="bedtime"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                            @error('bedtime')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 起床時間 -->
                        <div>
                            <label for="wakeup_time" class="block text-sm font-medium text-gray-700">
                                起床時間
                            </label>
                            <input type="time" wire:model="wakeup_time" wire:change="calculateSleepHours"
                                id="wakeup_time"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                            @error('wakeup_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 睡眠時間（自動計算または手動入力） -->
                        <div>
                            <label for="sleep_hours" class="block text-sm font-medium text-gray-700">
                                睡眠時間（時間）
                            </label>
                            <input type="number" wire:model="sleep_hours" id="sleep_hours" step="0.1"
                                min="0" max="24"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm"
                                placeholder="自動計算されるか、手動で入力してください">
                            @error('sleep_hours')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 睡眠の質 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                睡眠の質（1〜5）
                            </label>
                            <div class="flex space-x-4">
                                @for ($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" wire:model="sleep_quality" value="{{ $i }}"
                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ $i }}
                                            @if ($i == 1)
                                                (とても悪い)
                                            @elseif($i == 2)
                                                (悪い)
                                            @elseif($i == 3)
                                                (普通)
                                            @elseif($i == 4)
                                                (良い)
                                            @elseif($i == 5)
                                                (とても良い)
                                            @endif
                                        </span>
                                    </label>
                                @endfor
                            </div>
                            @error('sleep_quality')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- ボタン -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('sleep-logs.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                キャンセル
                            </a>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
