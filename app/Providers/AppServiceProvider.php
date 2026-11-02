<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\SchoolSetting;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                ActivityLog::record(ActivityLog::ACTION_LOGIN, 'masuk ke aplikasi', user: $event->user);
            }
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                ActivityLog::record(ActivityLog::ACTION_LOGOUT, 'keluar dari aplikasi', user: $event->user);
            }
        });

        View::composer(
            ['layouts.dashboard', 'auth.login', 'admin.dashboard.index', 'admin.teachers.index'],
            fn ($view) => $view->with('school', SchoolSetting::current()),
        );

        View::composer('layouts.dashboard', function ($view) {
            $user = Auth::user();

            $view->with(
                'layoutAnnouncementCount',
                $user ? Announcement::query()->visibleTo($user)->count() : 0,
            );
        });
    }
}
