<?php

namespace App\Providers;

use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;
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
        View::composer('layouts.dashboard', function ($view) {
            $user = Auth::user();

            $view->with(
                'layoutAnnouncementCount',
                $user ? Announcement::query()->visibleTo($user)->count() : 0,
            );
        });
    }
}
