<?php

namespace Khadija\LaravelSlotBooking;

use Illuminate\Support\ServiceProvider;
use Khadija\LaravelSlotBooking\Services\SlotBookingService; 
use Illuminate\Support\Facades\Route;
use Khadija\LaravelSlotBooking\Console\Commands\GenerateSlotsCommand; 
use Khadija\LaravelSlotBooking\Console\Commands\GenerateRecurringSlotsCommand; 

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

        // تسجيل الخدمة في Service Container
        $this->app->singleton('slot-booking', function ($app) {
            return new SlotBookingService();
        });

        // أضف هذا الجزء
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateSlotsCommand::class,
                GenerateRecurringSlotsCommand::class,
            ]);
        }
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

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        // لو أضفتي Migrations أو Views لاحقًا، ممكن تفعليهم هنا:
        // $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        // $this->loadViewsFrom(__DIR__.'/../resources/views', 'slot-booking');
    }
}
