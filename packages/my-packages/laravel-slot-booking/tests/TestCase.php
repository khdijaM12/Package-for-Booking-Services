<?php

namespace Khadija\LaravelSlotBooking\Tests;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
        protected function setUp(): void
    {
        parent::setUp();
        $this->defineDatabaseMigrations();
    }

    protected function getPackageProviders($app)
    {
        return [
            \Khadija\LaravelSlotBooking\SlotBookingServiceProvider::class, // غيّري الاسم حسب اسم مزود الخدمة عندك
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // هنا ممكن تضيفي إعدادات بيئة مخصصة
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
                $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
