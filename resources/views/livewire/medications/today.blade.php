<?php

use function Livewire\Volt\{state, rules, computed, mount};
use App\Models\DailyLog;
use App\Models\MedicationLog;

state([
    'todayDailyLog' => null,
    'medications' => [], // 今日の既存の服薬記録
    'newMedications' => [], // 新しく追加する服薬記録
]);

rules([
    'medications.*.taken' => 'boolean',
    'newMedications.*.medicine_name' => 'required|string|max:255',
    'newMedications.*.timing' => 'required|string|in:morning,afternoon,evening,bedtime,as_needed',
    'newMedications.*.taken' => 'boolean',
]);

mount(function () {
    $this->loadTodayData();
});

$loadTodayData = function () {
    // 今日の日次ログを取得または作成
    $this->todayDailyLog = DailyLog::firstOrCreate([
        'user_id' => auth()->id(),
        'date' => now()->toDateString(),
    ]);

    // 今日の服薬記録を取得
    $this->medications = $this->todayDailyLog->medicationLogs
        ->map(function ($log) {
            return [
                'id' => $log->id,
                'medicine_name' => $log->medicine_name,
                'timing' => $log->timing,
                'taken' => $log->taken,
            ];
        })
        ->toArray();

    // 新しい薬追加用の空配列を初期化
    if (empty($this->newMedications)) {
        $this->newMedications = [];
    }
};

$addNewMedication = function ($medicineName = '', $timing = '') {
    $this->newMedications[] = [
        'medicine_name' => $medicineName,
        'timing' => $timing,
        'taken' => false,
    ];
};

$removeNewMedication = function ($index) {
    unset($this->newMedications[$index]);
    $this->newMedications = array_values($this->newMedications);
};

$save = function () {
    $this->validate();

    // 既存の服薬記録を更新
    foreach ($this->medications as $medication) {
        if (isset($medication['id'])) {
            MedicationLog::where('id', $medication['id'])->update([
                'taken' => $medication['taken'],
            ]);
        }
    }

    // 新しい服薬記録を作成
    foreach ($this->newMedications as $newMedication) {
        if (!empty($newMedication['medicine_name']) && !empty($newMedication['timing'])) {
            MedicationLog::create([
                'daily_log_id' => $this->todayDailyLog->id,
                'medicine_name' => $newMedication['medicine_name'],
                'timing' => $newMedication['timing'],
                'taken' => $newMedication['taken'],
            ]);
        }
    }

    session()->flash('success', '今日の服薬記録を更新しました。');
    return redirect()->route('medications.index');
};

?>

<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('今日の服薬記録') }} - {{ now()->format('Y年m月d日 (D)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form wire:submit="save" class="space-y-8">
                        <!-- 既存の服薬記録 -->
                        @if (!empty($medications))
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">今日の服薬記録</h3>
                                @php
                                    $timingGroups = [
                                        'morning' => ['label' => '朝', 'icon' => '🌅', 'medications' => []],
                                        'afternoon' => ['label' => '昼', 'icon' => '☀️', 'medications' => []],
                                        'evening' => ['label' => '夜', 'icon' => '🌙', 'medications' => []],
                                        'bedtime' => ['label' => '就寝前', 'icon' => '😴', 'medications' => []],
                                        'as_needed' => ['label' => '頓服', 'icon' => '💊', 'medications' => []],
                                    ];

                                    foreach ($medications as $index => $medication) {
                                        $timing = $medication['timing'] ?? 'morning';
                                        if (isset($timingGroups[$timing])) {
                                            $timingGroups[$timing]['medications'][] = [
                                                'index' => $index,
                                                'medication' => $medication,
                                            ];
                                        }
                                    }
                                @endphp

                                <div class="space-y-6">
                                    @foreach ($timingGroups as $timingKey => $group)
                                        @if (!empty($group['medications']))
                                            <div
                                                class="border border-gray-200 rounded-lg p-4 {{ $timingKey === 'as_needed' ? 'bg-yellow-50' : 'bg-gray-50' }}">
                                                <h4 class="text-lg font-medium text-gray-900 mb-4 flex items-center">
                                                    <span class="mr-2">{{ $group['icon'] }}</span>
                                                    {{ $group['label'] }}
                                                    <span class="ml-2 text-sm text-gray-500">
                                                        ({{ count($group['medications']) }}種類)
                                                    </span>
                                                    @if ($timingKey === 'as_needed')
                                                        <span
                                                            class="ml-2 text-xs text-yellow-700 bg-yellow-200 px-2 py-1 rounded">
                                                            遵守率対象外
                                                        </span>
                                                    @endif
                                                </h4>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                    @foreach ($group['medications'] as $item)
                                                        @php
                                                            $index = $item['index'];
                                                            $medication = $item['medication'];
                                                        @endphp
                                                        <div
                                                            class="p-4 border border-gray-200 rounded-md 
                                                            {{ $medication['taken'] ? 'bg-green-50 border-green-200' : 'bg-white' }}">

                                                            <!-- 薬の名前 -->
                                                            <div class="mb-3">
                                                                <h5 class="font-medium text-gray-900 text-lg">
                                                                    {{ $medication['medicine_name'] }}
                                                                </h5>
                                                            </div>

                                                            <!-- Yes/No ボタン -->
                                                            <div class="flex items-center space-x-3 mb-3">
                                                                <span
                                                                    class="text-sm font-medium text-gray-700">服薬状況:</span>
                                                                <div class="flex space-x-2">
                                                                    <button type="button"
                                                                        wire:click="$set('medications.{{ $index }}.taken', true)"
                                                                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors
                                                                            {{ $medication['taken'] ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-green-100' }}">
                                                                        Yes
                                                                    </button>
                                                                    <button type="button"
                                                                        wire:click="$set('medications.{{ $index }}.taken', false)"
                                                                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors
                                                                            {{ !$medication['taken'] ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-red-100' }}">
                                                                        No
                                                                    </button>
                                                                </div>
                                                            </div>

                                                            <!-- 状態表示 -->
                                                            <div class="text-center">
                                                                <span
                                                                    class="text-sm font-medium {{ $medication['taken'] ? 'text-green-600' : 'text-red-600' }}">
                                                                    {{ $medication['taken'] ? '服薬済み' : '未服薬' }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif
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
                                <h3 class="mt-2 text-sm font-medium text-gray-900">今日の服薬記録がありません</h3>
                                <p class="mt-1 text-sm text-gray-500">下記から薬を追加してください。</p>
                            </div>
                        @endif

                        <!-- 新しい服薬記録追加 -->
                        @if (!empty($newMedications))
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">新しい服薬記録</h3>
                                <div class="space-y-6">
                                    @foreach ($newMedications as $index => $newMedication)
                                        <div class="p-6 border border-gray-200 rounded-lg bg-blue-50">
                                            <!-- 薬の名前入力 -->
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                                    薬の名前
                                                </label>
                                                <input type="text"
                                                    wire:model="newMedications.{{ $index }}.medicine_name"
                                                    placeholder="薬の名前を入力してください"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                                @error("newMedications.{$index}.medicine_name")
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- 服薬タイミング選択 -->
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium text-gray-700 mb-3">
                                                    服薬タイミング
                                                </label>
                                                <select wire:model="newMedications.{{ $index }}.timing"
                                                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-purple-500 focus:ring-purple-500 sm:text-sm">
                                                    <option value="">タイミングを選択してください</option>
                                                    <option value="morning">朝</option>
                                                    <option value="afternoon">昼</option>
                                                    <option value="evening">晩</option>
                                                    <option value="bedtime">就寝前</option>
                                                    <option value="as_needed">頓服</option>
                                                </select>
                                                @error("newMedications.{$index}.timing")
                                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <!-- Yes/No ボタン -->
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center space-x-3">
                                                    <span class="text-sm font-medium text-gray-700">服薬状況:</span>
                                                    <div class="flex space-x-2">
                                                        <button type="button"
                                                            wire:click="$set('newMedications.{{ $index }}.taken', true)"
                                                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors
                                                                {{ $newMedication['taken'] ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-green-100' }}">
                                                            Yes
                                                        </button>
                                                        <button type="button"
                                                            wire:click="$set('newMedications.{{ $index }}.taken', false)"
                                                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors
                                                                {{ !$newMedication['taken'] ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-red-100' }}">
                                                            No
                                                        </button>
                                                    </div>

                                                    <span
                                                        class="text-sm font-medium 
                                                        {{ $newMedication['taken'] ? 'text-green-600' : 'text-red-600' }}">
                                                        {{ $newMedication['taken'] ? '服薬済み' : '未服薬' }}
                                                    </span>
                                                </div>

                                                <button type="button"
                                                    wire:click="removeNewMedication({{ $index }})"
                                                    class="text-red-600 hover:text-red-800 p-2">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- 薬を追加ボタン -->
                        <div class="flex justify-center">
                            <button type="button" wire:click="addNewMedication"
                                class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-medium rounded-md text-purple-700 bg-purple-100 hover:bg-purple-200 transition-colors">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                薬を追加
                            </button>
                        </div>

                        <!-- よく使用する薬のクイック追加 -->
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-3">よく使用する薬（クリックで追加）</h4>
                            @php
                                $commonMedicines = [
                                    ['name' => 'リスペリドン', 'timing' => 'morning'],
                                    ['name' => 'リスペリドン', 'timing' => 'evening'],
                                    ['name' => 'セルトラリン', 'timing' => 'morning'],
                                    ['name' => 'ロラゼパム', 'timing' => 'as_needed'],
                                    ['name' => 'メラトニン', 'timing' => 'bedtime'],
                                    ['name' => 'オメプラゾール', 'timing' => 'morning'],
                                ];

                                $timingLabels = [
                                    'morning' => '朝',
                                    'afternoon' => '昼',
                                    'evening' => '晩',
                                    'bedtime' => '就寝前',
                                    'as_needed' => '頓服',
                                ];
                            @endphp
                            <div class="flex flex-wrap gap-2">
                                @foreach ($commonMedicines as $medicine)
                                    <button type="button"
                                        wire:click="addNewMedication('{{ $medicine['name'] }}', '{{ $medicine['timing'] }}')"
                                        class="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-full hover:bg-gray-200 transition-colors">
                                        {{ $medicine['name'] }}（{{ $timingLabels[$medicine['timing']] }}）
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- ボタン -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('medications.index') }}"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                キャンセル
                            </a>
                            <button type="submit"
                                class="px-6 py-2 text-sm font-medium text-white bg-purple-600 border border-transparent rounded-md shadow-sm hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-purple-500">
                                保存
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
