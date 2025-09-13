@props(['score', 'size' => 'base'])

@php
    $moodIcons = [
        1 => '😢', // とても悪い
        2 => '😞', // 悪い
        3 => '😐', // 普通
        4 => '😊', // 良い
        5 => '😄', // とても良い
    ];

    $sizeClasses = [
        'sm' => 'text-sm',
        'base' => 'text-base',
        'lg' => 'text-lg',
        'xl' => 'text-xl',
        '2xl' => 'text-2xl',
        '3xl' => 'text-3xl',
    ];

    $icon = $moodIcons[$score] ?? '❓';
    $sizeClass = $sizeClasses[$size] ?? $sizeClasses['base'];
@endphp

<span class="{{ $sizeClass }}" title="気分スコア: {{ $score }}/5">{{ $icon }}</span>
