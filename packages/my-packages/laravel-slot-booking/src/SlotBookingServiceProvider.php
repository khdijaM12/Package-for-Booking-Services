<?php

namespace Khadija\LaravelSlotBooking;

use Illuminate\Support\ServiceProvider;

class SlotBookingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register()
    {
        // تسجيل ملف الإعدادات إذا كان موجود
        $this->mergeConfigFrom(
            __DIR__ . '/../config/slot-booking.php', 'slot-booking'
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        // نشر ملف الإعدادات لتتمكني من تخصيصه في مشروع Laravel
        $this->publishes([
            __DIR__ . '/../config/slot-booking.php' => config_path('slot-booking.php'),
        ], 'slot-booking-config');

        // لو أضفتي Migrations أو Views لاحقًا، ممكن تفعليهم هنا:
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'slot-booking');
    }
}
