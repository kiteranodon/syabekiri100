<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// ウェルカムページ
Route::get('/', function () {
    return view('welcome');
})->name('home');

// 認証が必要な画面群
Route::middleware(['auth', 'verified'])->group(function () {
    // ダッシュボード（メイン画面）
    Volt::route('dashboard', 'dashboard.index')->name('dashboard');

    // 日次ログ関連
    Volt::route('daily-logs', 'daily-logs.index')->name('daily-logs.index');
    Volt::route('daily-logs/create', 'daily-logs.create')->name('daily-logs.create');
    Volt::route('daily-logs/{date}/edit', 'daily-logs.edit')->name('daily-logs.edit');

    // 睡眠ログ関連
    Volt::route('sleep-logs', 'sleep-logs.index')->name('sleep-logs.index');
    Volt::route('sleep-logs/create', 'sleep-logs.create')->name('sleep-logs.create');

    // 服薬管理関連
    Volt::route('medications', 'medications.index')->name('medications.index');
    Volt::route('medications/create', 'medications.create')->name('medications.create');
    Volt::route('medications/today', 'medications.today')->name('medications.today');

    // レポート関連
    Volt::route('reports', 'reports.index')->name('reports.index');
    Volt::route('reports/create', 'reports.create')->name('reports.create');
    Volt::route('reports/{report}', 'reports.show')->name('reports.show');

    // 診察予約関連（オプション）
    Volt::route('appointments', 'appointments.index')->name('appointments.index');
    Volt::route('appointments/create', 'appointments.create')->name('appointments.create');

    // 設定関連
    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__ . '/auth.php';
