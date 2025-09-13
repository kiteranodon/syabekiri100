<?php

use function Livewire\Volt\{state, rules, mount};
use App\Models\MedicationLog;

state([
    'medication' => null,
    'medicine_name' => '',
    'timing' => '',
    'taken' => false,
]);

rules([
    'medicine_name' => 'required|string|max:255',
    'timing' => 'required|string|in:morning,afternoon,evening,bedtime,as_needed',
    'taken' => 'boolean',
]);

mount(function ($id) {
    $this->medication = MedicationLog::whereHas('dailyLog', function ($query) {
        $query->where('user_id', auth()->id());
    })->findOrFail($id);

    $this->medicine_name = $this->medication->medicine_name;
    $this->timing = $this->medication->timing;
    $this->taken = $this->medication->taken;
});

$save = function () {
    $this->validate();

    $this->medication->update([
        'medicine_name' => $this->medicine_name,
        'timing' => $this->timing,
        'taken' => $this->taken,
    ]);

    session()->flash('success', '服薬記録を更新しました。');
    return redirect()->route('medications.history');
};

$getTimingOptions = function () {
    return [
        'morning' => '朝',
        'afternoon' => '昼',
        'evening' => '晩',
        'bedtime' => '就寝前',
        'as_needed' => '頓服',
    ];
};

?>

<div>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('服薬記録の編集') }}</h2>
            <div class="flex space-x-3">
                <a href="{{ route('medications.history') }}"
                    class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    戻る
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-2">服薬記録の編集</h3>
                        <p class="text-sm text-gray-600">
                            記録日: {{ $medication->dailyLog->date->format('Y年m月d日 (D)') }}
                        </p>
                    </div>

                    <form wire:submit.prevent="save" class="space-y-6">
                        <!-- 薬名 -->
                        <div>
                            <label for="medicine_name" class="block text-sm font-medium text-gray-700 mb-2">
                                薬名 <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="medicine_name" wire:model="medicine_name"
                                placeholder="薬名を入力してください"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                                @error('medicine_name') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                            @error('medicine_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- タイミング -->
                        <div>
                            <label for="timing" class="block text-sm font-medium text-gray-700 mb-2">
                                タイミング <span class="text-red-500">*</span>
                            </label>
                            <select id="timing" wire:model="timing"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm
                                @error('timing') border-red-300 focus:border-red-500 focus:ring-red-500 @enderror">
                                <option value="">タイミングを選択してください</option>
                                @foreach ($this->getTimingOptions() as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('timing')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- 服薬状況 -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-3">服薬状況</label>
                            <div class="flex rounded-md overflow-hidden border border-gray-300">
                                <button type="button" wire:click="$set('taken', false)"
                                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors
                                        {{ !$taken ? 'bg-red-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                                    <div class="flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        未服薬
                                    </div>
                                </button>
                                <button type="button" wire:click="$set('taken', true)"
                                    class="flex-1 px-4 py-3 text-sm font-medium transition-colors border-l border-gray-300
                                        {{ $taken ? 'bg-green-500 text-white' : 'bg-white text-gray-700 hover:bg-gray-50' }}">
                                    <div class="flex items-center justify-center">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M5 13l4 4L19 7" />
                                        </svg>
                                        服薬済み
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- 現在の状況表示 -->
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-gray-900 mb-2">現在の設定</h4>
                            <div class="space-y-1 text-sm text-gray-600">
                                <p><span class="font-medium">薬名:</span> {{ $medicine_name ?: '未入力' }}</p>
                                <p><span class="font-medium">タイミング:</span>
                                    {{ $timing ? $this->getTimingOptions()[$timing] : '未選択' }}
                                </p>
                                <p><span class="font-medium">状況:</span>
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $taken ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ $taken ? '服薬済み' : '未服薬' }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- ボタン -->
                        <div class="flex justify-end space-x-3 pt-6">
                            <a href="{{ route('medications.history') }}"
                                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                キャンセル
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7" />
                                </svg>
                                更新
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
