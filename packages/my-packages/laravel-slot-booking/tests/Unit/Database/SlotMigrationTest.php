<?php

namespace Khadija\LaravelSlotBooking\Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Khadija\LaravelSlotBooking\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SlotMigrationTest extends TestCase
{
    use RefreshDatabase;

protected function setUp(): void
{
    parent::setUp();

    // شغلي الميجريشن للباكدج (تأكد من المسار مضبوط على مشروعك)
    $this->artisan('migrate', [
        '--path' => 'packages/my-packages/laravel-slot-booking/database/migrations',
        '--realpath' => true, // يساعد لو كان المسار مطلق أو نسق مختلف
    ])->run();
}


    /** @test */
    public function slots_table_has_expected_columns()
    {
        $tableName = config('slot-booking.tables.slots', 'slots');

        $this->assertTrue(Schema::hasTable($tableName), "جدول $tableName غير موجود");

        foreach(['id', 'service_id', 'start_time', 'end_time', 'is_available', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(Schema::hasColumn($tableName, $column), "العمود مفقود: $column");
        }
    }

    /** @test */
    public function slots_table_has_index_on_service_id_and_time_columns()
    {
        $tableName = config('slot-booking.tables.slots', 'slots');
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            // استخدام استعلام خاص بـ SQLite
            $indexes = DB::select("PRAGMA index_list('$tableName')");
            $indexNames = array_map(fn($idx) => $idx->name, $indexes);

            $this->assertNotEmpty($indexNames, "لا توجد فهارس (indexes) على الجدول");

            // غيّري اسم الـ index هنا حسب ما أنشأتيه في الميجريشن
            $expectedIndex = "{$tableName}_service_id_start_time_end_time_index";
            $this->assertContains($expectedIndex, $indexNames, "الفهرس $expectedIndex غير موجود");
        } else {
            // قواعد بيانات MySQL، PostgreSQL، الخ
            $index = DB::select("
                SELECT INDEX_NAME 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_NAME = ? 
                  AND COLUMN_NAME IN ('service_id', 'start_time', 'end_time')
            ", [$tableName]);

            $this->assertNotEmpty($index, "Missing index on service_id, start_time, or end_time");
        }
    }
}
