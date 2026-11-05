<?php

use App\Console\Commands\BackupDatabase;
use App\Models\ActivityLog;
use App\Models\Announcement;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune', ['--model' => [ActivityLog::class, Announcement::class]])->daily();
Schedule::command(BackupDatabase::class)->dailyAt('01:00');
