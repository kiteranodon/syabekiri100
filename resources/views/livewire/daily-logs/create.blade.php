<?php

use function Livewire\Volt\{state, rules};
use App\Models\DailyLog;

state([
    'date' => now()->toDateString(),
    'mood_score' => null,
    'free_note' => '',
]);

rules([
    'date' => 'required|date',
    'mood_score' => 'nullable|integer|min:1|max:5',
    'free_note' => 'nullable|string|max:140',
]);

$save = function () {
    $this->validate();

    // 既存のログがあるかチェック
    $existingLog = DailyLog::where('user_id', auth()->id())
        ->where('date', $this->date)
        ->first();

    if ($existingLog) {
        session()->flash('error', 'この日の記録は既に存在します。編集ページから更新してください。');
        return;
    }

    DailyLog::create([
        'user_id' => auth()->id(),
        'date' => $this->date,
        'mood_score' => $this->mood_score,
        'free_note' => $this->free_note,
    ]);

    session()->flash('success', '日次ログを保存しました。');
    return redirect()->route('daily-logs.index');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('日次ログ作成') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="save" class="space-y-6">
                        <!-- 日付選択 -->
                        <div>
                            <label for="date" class="block text-sm font-medium text-gray-700">
                                記録日
                            </label>
                            <input type="date" wire:model="date" id="date"
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                            @error('date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 気分スコア -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">
                                気分スコア（1〜5）
                            </label>
                            <div class="flex flex-wrap gap-4">
                                @for ($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center cursor-pointer">
                                        <input type="radio" wire:model="mood_score" value="{{ $i }}"
                                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300">
                                        <span class="ml-2 text-sm text-gray-700 flex items-center">
                                            <x-mood-icon :score="$i" size="lg" />
                                            <span class="ml-1">{{ $i }}</span>
                                            @if ($i == 1)
                                                <span class="ml-1">(とても悪い)</span>
                                            @elseif($i == 2)
                                                <span class="ml-1">(悪い)</span>
                                            @elseif($i == 3)
                                                <span class="ml-1">(普通)</span>
                                            @elseif($i == 4)
                                                <span class="ml-1">(良い)</span>
                                            @elseif($i == 5)
                                                <span class="ml-1">(とても良い)</span>
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

                        <!-- ボタン -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('daily-logs.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                キャンセル
                            </a>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md shadow-sm hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
