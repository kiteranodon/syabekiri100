<?php

use function Livewire\Volt\{state, rules, mount};
use App\Models\SleepLog;
use App\Models\DailyLog;

state([
    'sleepLog' => null,
    'selected_date' => null,
    'bedtime' => null,
    'bedtime_hour' => null,
    'bedtime_minute' => null,
    'wakeup_time' => null,
    'wakeup_hour' => null,
    'wakeup_minute' => null,
    'sleep_hours' => null,
    'sleep_quality' => null,
]);

rules([
    'selected_date' => 'required|date|before_or_equal:today',
    'bedtime' => 'nullable|date_format:H:i',
    'wakeup_time' => 'nullable|date_format:H:i',
    'sleep_hours' => 'nullable|numeric|min:0|max:24',
    'sleep_quality' => 'nullable|integer|min:1|max:5',
]);

mount(function ($id) {
    $this->sleepLog = SleepLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })->findOrFail($id);

    // 既存データを設定
    $this->selected_date = $this->sleepLog->dailyLog->date->toDateString();
    $this->sleep_hours = $this->sleepLog->sleep_hours;
    $this->sleep_quality = $this->sleepLog->sleep_quality;

    // 時間データを分解
    if ($this->sleepLog->bedtime) {
        $bedtime = \Carbon\Carbon::parse($this->sleepLog->bedtime);
        $this->bedtime = $bedtime->format('H:i');
        $this->bedtime_hour = $bedtime->format('H');
        $this->bedtime_minute = $bedtime->format('i');
    }

    if ($this->sleepLog->wakeup_time) {
        $wakeupTime = \Carbon\Carbon::parse($this->sleepLog->wakeup_time);
        $this->wakeup_time = $wakeupTime->format('H:i');
        $this->wakeup_hour = $wakeupTime->format('H');
        $this->wakeup_minute = $wakeupTime->format('i');
    }
});

$updateBedtime = function () {
    if ($this->bedtime_hour !== null && $this->bedtime_minute !== null) {
        $this->bedtime = sprintf('%02d:%02d', $this->bedtime_hour, $this->bedtime_minute);
        $this->calculateSleepHours();
    }
};

$updateWakeupTime = function () {
    if ($this->wakeup_hour !== null && $this->wakeup_minute !== null) {
        $this->wakeup_time = sprintf('%02d:%02d', $this->wakeup_hour, $this->wakeup_minute);
        $this->calculateSleepHours();
    }
};

$calculateSleepHours = function () {
    if ($this->bedtime && $this->wakeup_time) {
        $bedtime = \Carbon\Carbon::createFromFormat('H:i', $this->bedtime);
        $wakeupTime = \Carbon\Carbon::createFromFormat('H:i', $this->wakeup_time);

        // 起床時間が就寝時間より早い場合は翌日とみなす
        if ($wakeupTime->lt($bedtime)) {
            $wakeupTime->addDay();
        }

        $diffInMinutes = $bedtime->diffInMinutes($wakeupTime, false);
        $this->sleep_hours = round($diffInMinutes / 60, 1);
    }
};

$getSleepDurationFormatted = computed(function () {
    if (!$this->sleep_hours) {
        return '';
    }
    $totalMinutes = round($this->sleep_hours * 60);
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    return $hours . '時間' . $minutes . '分';
});

$update = function () {
    $this->validate();

    $this->sleepLog->update([
        'bedtime' => $this->bedtime,
        'wakeup_time' => $this->wakeup_time,
        'sleep_hours' => $this->sleep_hours,
        'sleep_quality' => $this->sleep_quality,
    ]);

    session()->flash('success', '睡眠ログを更新しました。');
    return redirect()->route('sleep-logs.index');
};

$delete = function () {
    $this->sleepLog->delete();
    session()->flash('success', '睡眠ログを削除しました。');
    return redirect()->route('sleep-logs.index');
};

$cancel = function () {
    return redirect()->route('sleep-logs.index');
};

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('睡眠ログ編集') }} - {{ $sleepLog->dailyLog->date->format('Y年m月d日') }}
            </h2>
            <a href="{{ route('sleep-logs.index') }}"
                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                戻る
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="update" class="space-y-6">
                        <!-- 就寝時間 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">就寝時間</label>
                            <div class="flex space-x-2">
                                <!-- 時間 -->
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">時</label>
                                    <select wire:model.live="bedtime_hour" wire:change="updateBedtime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($i = 0; $i <= 23; $i++)
                                            <option value="{{ $i }}">{{ sprintf('%02d', $i) }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <!-- 分 -->
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">分</label>
                                    <select wire:model.live="bedtime_minute" wire:change="updateBedtime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($i = 0; $i <= 55; $i += 5)
                                            <option value="{{ $i }}">{{ sprintf('%02d', $i) }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            @error('bedtime')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 起床時間 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">起床時間</label>
                            <div class="flex space-x-2">
                                <!-- 時間 -->
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">時</label>
                                    <select wire:model.live="wakeup_hour" wire:change="updateWakeupTime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($i = 0; $i <= 23; $i++)
                                            <option value="{{ $i }}">{{ sprintf('%02d', $i) }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <!-- 分 -->
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">分</label>
                                    <select wire:model.live="wakeup_minute" wire:change="updateWakeupTime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($i = 0; $i <= 55; $i += 5)
                                            <option value="{{ $i }}">{{ sprintf('%02d', $i) }}</option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            @error('wakeup_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 睡眠時間（自動計算） -->
                        @if ($this->getSleepDurationFormatted)
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">睡眠時間（自動計算）</label>
                                <div class="p-3 bg-blue-50 rounded-md">
                                    <p class="text-lg font-semibold text-blue-900">
                                        {{ $this->getSleepDurationFormatted }}</p>
                                </div>
                            </div>
                        @endif

                        <!-- 睡眠の質 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">睡眠の質</label>
                            <div class="flex space-x-4">
                                @for ($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center cursor-pointer">
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
                        <div class="flex justify-between">
                            <button type="button" wire:click="delete" wire:confirm="この睡眠ログを削除してもよろしいですか？"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                削除
                            </button>

                            <div class="flex space-x-3">
                                <button type="button" wire:click="cancel"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    キャンセル
                                </button>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                    更新
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
