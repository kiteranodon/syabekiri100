<?php

use function Livewire\Volt\{state, rules, computed, mount};
use App\Models\DailyLog;
use App\Models\SleepLog;

state([
    'selected_date' => null,
    'bedtime' => null,
    'bedtime_hour' => null,
    'bedtime_minute' => null,
    'wakeup_time' => null,
    'wakeup_hour' => null,
    'wakeup_minute' => null,
    'sleep_hours' => null,
    'sleep_quality' => null,
    'flow' => null,
]);

rules([
    'selected_date' => 'required|date|before_or_equal:today',
    'bedtime' => 'nullable|date_format:H:i',
    'wakeup_time' => 'nullable|date_format:H:i',
    'sleep_hours' => 'nullable|numeric|min:0|max:24',
    'sleep_quality' => 'nullable|integer|min:1|max:5',
]);

mount(function () {
    // URLパラメータから日付を取得、なければ今日をデフォルト
    $this->selected_date = request('date', now()->toDateString());
    $this->flow = request('flow');
});

// 就寝時間を更新
$updateBedtime = function () {
    if ($this->bedtime_hour && $this->bedtime_minute) {
        $this->bedtime = $this->bedtime_hour . ':' . $this->bedtime_minute;
        $this->calculateSleepHours();
    }
};

// 起床時間を更新
$updateWakeupTime = function () {
    if ($this->wakeup_hour && $this->wakeup_minute) {
        $this->wakeup_time = $this->wakeup_hour . ':' . $this->wakeup_minute;
        $this->calculateSleepHours();
    }
};

// クイック日付選択のトグル機能
$toggleQuickDate = function ($date) {
    if ($this->selected_date === $date) {
        // 同じ日付を再選択した場合は今日に戻す
        $this->selected_date = now()->toDateString();
    } else {
        // 別の日付を選択
        $this->selected_date = $date;
    }
};

// 過去90日間で睡眠ログが未記入の日付を取得
$availableDates = computed(function () {
    $startDate = now()->subDays(90);
    $endDate = now();

    // 既に睡眠ログがある日付を取得
    $existingSleepLogDates = SleepLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })
        ->with('dailyLog')
        ->get()
        ->pluck('dailyLog.date')
        ->map(fn($date) => $date->toDateString())
        ->toArray();

    // 過去90日間の全日付から既存の睡眠ログ日付を除外
    $availableDates = [];
    $currentDate = $startDate->copy();

    while ($currentDate->lte($endDate)) {
        $dateString = $currentDate->toDateString();
        if (!in_array($dateString, $existingSleepLogDates)) {
            $availableDates[] = $dateString;
        }
        $currentDate->addDay();
    }

    return $availableDates;
});

$save = function () {
    $this->validate();

    // 選択された日付の気分記録を取得または作成
    $dailyLog = DailyLog::firstOrCreate([
        'user_id' => auth()->id(),
        'date' => $this->selected_date,
    ]);

    // 既に睡眠ログが存在するかチェック
    if ($dailyLog->sleepLog) {
        session()->flash('error', 'この日付の睡眠ログは既に存在します。');
        return;
    }

    $sleepLog = SleepLog::create([
        'daily_log_id' => $dailyLog->id,
        'bedtime' => $this->bedtime,
        'wakeup_time' => $this->wakeup_time,
        'sleep_hours' => $this->sleep_hours,
        'sleep_quality' => $this->sleep_quality,
    ]);

    if ($this->flow === 'batch') {
        session()->flash('success', '睡眠ログを保存しました。次に服薬管理を確認してください。');
        return redirect()->route('medications.index', ['flow' => 'batch', 'date' => $this->selected_date]);
    }

    session()->flash('success', '睡眠ログを保存しました。');
    return redirect()->route('sleep-logs.index');
};

// 睡眠時間を自動計算
$calculateSleepHours = function () {
    if ($this->bedtime && $this->wakeup_time) {
        $bedtime = \Carbon\Carbon::createFromFormat('H:i', $this->bedtime);
        $wakeupTime = \Carbon\Carbon::createFromFormat('H:i', $this->wakeup_time);

        // 起床時間が就寝時間より早い場合は翌日とみなす
        if ($wakeupTime->lt($bedtime)) {
            $wakeupTime->addDay();
        }

        $sleepHours = $bedtime->diffInHours($wakeupTime, false);
        $sleepMinutes = $bedtime->diffInMinutes($wakeupTime, false) % 60;

        $this->sleep_hours = round($sleepHours + $sleepMinutes / 60, 1);
    }
};

// 睡眠時間を時間・分形式で取得
$getSleepDurationFormatted = computed(function () {
    if (!$this->sleep_hours) {
        return '';
    }

    $totalMinutes = round($this->sleep_hours * 60);
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;

    return $hours . '時間' . $minutes . '分';
});

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            @if ($flow === 'batch')
                {{ __('まとめて記入 - 睡眠記録 (2/3)') }}
            @else
                {{ __('睡眠ログ作成') }}
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="save" class="space-y-6">
                        <!-- 記録日選択 -->
                        <div>
                            <label for="selected_date" class="block text-sm font-medium text-gray-700 mb-2">
                                記録日
                            </label>
                            <input type="date" wire:model="selected_date" id="selected_date"
                                max="{{ now()->toDateString() }}"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                            @error('selected_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <!-- 利用可能な日付の説明 -->
                            <div class="mt-2 p-3 bg-blue-50 rounded-md">
                                <p class="text-sm text-blue-800">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                    デフォルトは今日です。過去90日間で睡眠ログが未記入の日付を選択できます。
                                </p>
                                @if (count($this->availableDates) > 1)
                                    <p class="text-xs text-blue-600 mt-1">
                                        記録可能な日付: {{ count($this->availableDates) }}日分
                                    </p>
                                @endif
                            </div>

                            <!-- よく選ばれる日付のクイック選択 -->
                            @if (count($this->availableDates) > 1)
                                <div class="mt-3">
                                    <p class="text-sm font-medium text-gray-700 mb-2">クイック選択:</p>
                                    <div class="flex flex-wrap gap-2">
                                        @php
                                            $quickDates = [
                                                now()->subDay()->toDateString() => '昨日',
                                                now()->subDays(2)->toDateString() => '一昨日',
                                                now()->subDays(3)->toDateString() => '三日前',
                                            ];
                                        @endphp
                                        @foreach ($quickDates as $date => $label)
                                            @if (in_array($date, $this->availableDates))
                                                <button type="button"
                                                    wire:click="toggleQuickDate('{{ $date }}')"
                                                    class="px-3 py-1 text-sm {{ $selected_date === $date ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-green-100' }} rounded-full transition-colors">
                                                    {{ $label }}
                                                </button>
                                            @endif
                                        @endforeach

                                        <!-- 今日に戻すボタン -->
                                        @if ($selected_date !== now()->toDateString())
                                            <button type="button"
                                                wire:click="$set('selected_date', '{{ now()->toDateString() }}')"
                                                class="px-3 py-1 text-sm bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-full transition-colors">
                                                今日に戻す
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <!-- 就寝時間 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                就寝時間
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <!-- 時間選択 -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">時</label>
                                    <select wire:model="bedtime_hour" wire:change="updateBedtime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($h = 0; $h <= 23; $h++)
                                            <option value="{{ sprintf('%02d', $h) }}">{{ $h }}時</option>
                                        @endfor
                                    </select>
                                </div>

                                <!-- 分選択 -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">分</label>
                                    <select wire:model="bedtime_minute" wire:change="updateBedtime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($m = 0; $m < 60; $m += 5)
                                            <option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}分
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            @if (isset($bedtime_hour) && isset($bedtime_minute))
                                <p class="mt-2 text-sm text-green-600 font-medium">
                                    就寝時間: {{ $bedtime_hour }}:{{ $bedtime_minute }}
                                </p>
                            @endif
                            @error('bedtime')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 起床時間 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                起床時間
                            </label>
                            <div class="grid grid-cols-2 gap-4">
                                <!-- 時間選択 -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">時</label>
                                    <select wire:model="wakeup_hour" wire:change="updateWakeupTime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($h = 0; $h <= 23; $h++)
                                            <option value="{{ sprintf('%02d', $h) }}">{{ $h }}時</option>
                                        @endfor
                                    </select>
                                </div>

                                <!-- 分選択 -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 mb-1">分</label>
                                    <select wire:model="wakeup_minute" wire:change="updateWakeupTime"
                                        class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm">
                                        <option value="">--</option>
                                        @for ($m = 0; $m < 60; $m += 5)
                                            <option value="{{ sprintf('%02d', $m) }}">{{ sprintf('%02d', $m) }}分
                                            </option>
                                        @endfor
                                    </select>
                                </div>
                            </div>
                            @if (isset($wakeup_hour) && isset($wakeup_minute))
                                <p class="mt-2 text-sm text-green-600 font-medium">
                                    起床時間: {{ $wakeup_hour }}:{{ $wakeup_minute }}
                                </p>
                            @endif
                            @error('wakeup_time')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 睡眠時間（自動計算） -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                睡眠時間
                            </label>
                            @if ($this->getSleepDurationFormatted)
                                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-lg font-semibold text-green-800">
                                            {{ $this->getSleepDurationFormatted }}
                                        </span>
                                    </div>
                                    <p class="text-sm text-green-600 mt-1">
                                        就寝・起床時間から自動計算されました
                                    </p>
                                </div>
                            @else
                                <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span class="text-gray-600">
                                            就寝・起床時間を選択すると自動計算されます
                                        </span>
                                    </div>
                                </div>
                            @endif

                            <!-- 手動入力も可能 -->
                            <div class="mt-4">
                                <label class="block text-xs font-medium text-gray-500 mb-2">
                                    手動で修正する場合（小数点可）
                                </label>
                                <input type="number" step="0.1" wire:model="sleep_hours"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 sm:text-sm"
                                    placeholder="例: 7.5">
                                @error('sleep_hours')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- 睡眠の質 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                睡眠の質（1〜5段階）
                            </label>
                            <div class="flex space-x-4">
                                @for ($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" wire:model="sleep_quality" value="{{ $i }}"
                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700">
                                            {{ $i }}
                                            @if ($i == 1)
                                                （悪い）
                                            @elseif($i == 3)
                                                （普通）
                                            @elseif($i == 5)
                                                （良い）
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
