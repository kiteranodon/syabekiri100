<?php

use function Livewire\Volt\{state, rules, mount};
use App\Models\DailyLog;

state([
    'date' => '',
    'mood_score' => null,
    'free_note' => '',
    'dailyLog' => null,
]);

rules([
    'mood_score' => 'nullable|integer|min:1|max:5',
    'free_note' => 'nullable|string|max:140',
]);

mount(function ($date) {
    $this->dailyLog = DailyLog::where('user_id', auth()->id())
        ->where('date', $date)
        ->firstOrFail();

    $this->date = $this->dailyLog->date->format('Y-m-d');
    $this->mood_score = $this->dailyLog->mood_score;
    $this->free_note = $this->dailyLog->free_note;
});

$save = function () {
    $this->validate();

    $this->dailyLog->update([
        'mood_score' => $this->mood_score,
        'free_note' => $this->free_note,
    ]);

    session()->flash('success', '日次ログを更新しました。');
    return redirect()->route('daily-logs.index');
};

$delete = function () {
    $this->dailyLog->delete();

    session()->flash('success', '日次ログを削除しました。');
    return redirect()->route('daily-logs.index');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('日次ログ編集') }} - {{ $this->dailyLog->date->format('Y年m月d日') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="save" class="space-y-6">
                        <!-- 日付表示 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                記録日
                            </label>
                            <div
                                class="mt-1 block w-full px-3 py-2 bg-gray-50 border border-gray-300 rounded-md text-gray-700">
                                {{ $this->dailyLog->date->format('Y年m月d日 (D)') }}
                            </div>
                        </div>

                        <!-- 気分スコア -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                気分スコア（1〜5）
                            </label>
                            <div class="flex space-x-4">
                                @for ($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center">
                                        <input type="radio" wire:model="mood_score" value="{{ $i }}"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
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
                            @error('mood_score')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 自由日記 -->
                        <div>
                            <label for="free_note" class="block text-sm font-medium text-gray-700">
                                自由日記（140字以内）
                            </label>
                            <div class="mt-1">
                                <textarea wire:model="free_note" id="free_note" rows="4"
                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                    placeholder="今日の出来事や気持ちを自由に記録してください..."></textarea>
                                <div class="mt-1 flex justify-between text-xs text-gray-500">
                                    <span>{{ mb_strlen($free_note) }}/140文字</span>
                                </div>
                            </div>
                            @error('free_note')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 関連データ情報 -->
                        @if ($this->dailyLog->sleepLog || $this->dailyLog->medicationLogs->count() > 0)
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <h4 class="font-medium text-blue-900 mb-2">関連する記録</h4>
                                <div class="text-sm text-blue-800">
                                    @if ($this->dailyLog->sleepLog)
                                        <p>• 睡眠ログ: {{ $this->dailyLog->sleepLog->sleep_hours }}時間</p>
                                    @endif
                                    @if ($this->dailyLog->medicationLogs->count() > 0)
                                        <p>• 服薬記録: {{ $this->dailyLog->medicationLogs->count() }}件</p>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <!-- ボタン -->
                        <div class="flex justify-between">
                            <button type="button" wire:click="delete"
                                wire:confirm="この日次ログを削除してもよろしいですか？関連する睡眠ログや服薬記録も削除されます。"
                                class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                                削除
                            </button>

                            <div class="flex space-x-3">
                                <a href="{{ route('daily-logs.index') }}"
                                    class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                    キャンセル
                                </a>
                                <button type="submit"
                                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
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
