<?php

namespace Khadija\LaravelSlotBooking\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
    }
}
