<?php

namespace Khadija\LaravelSlotBooking\Tests;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\SanctumServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;
    
        protected function setUp(): void
    {
        parent::setUp();
        $this->defineDatabaseMigrations();
        $this->setUpDatabase($this->app);
    }

    protected function getPackageProviders($app)
    {
        return [
            \Khadija\LaravelSlotBooking\SlotBookingServiceProvider::class, // غيّري الاسم حسب اسم مزود الخدمة عندك
            SanctumServiceProvider::class,
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

         $app['config']->set('auth.guards.api', [
            'driver' => 'sanctum',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users.model', \Illuminate\Foundation\Auth\User::class);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

        /**
     * إعداد قاعدة بيانات إضافية للاختبار.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function setUpDatabase($app)
    {
        // Orchestra Testbench يقوم بإنشاء جدول المستخدمين افتراضيًا
        // لكن إذا احتجتِ تعديله أو إضافة حقول، يمكنكِ فعل ذلك هنا
        // مثال:
        /*
        $app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
        */
    }
}
