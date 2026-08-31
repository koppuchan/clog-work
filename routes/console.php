<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| スケジュールタスク
|--------------------------------------------------------------------------
|
| バッチ処理のスケジュール設定
|
*/

// 日次勤務実績集計バッチ: 毎日0時（24時）に実行
Schedule::command('batch:aggregate-daily-work-summaries')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/batch-daily-work-summaries.log'));
