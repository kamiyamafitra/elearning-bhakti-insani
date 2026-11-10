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
use Illuminate\Support\Facades\URL;
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
        // On the live https site every generated link and redirect stays on https.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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

        // The sidebar of each role shows a red badge with the number of new announcements.
        View::composer(
            ['admin.layouts.dashboard', 'teacher.layouts.dashboard', 'student.layouts.dashboard'],
            function ($view) {
                $user = Auth::user();

                $view->with('newAnnouncementCount', $user ? Announcement::query()->unreadBy($user)->count() : 0);
            },
        );
    }
}
