<?php

namespace Khadija\LaravelSlotBooking\Tests\Unit;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Khadija\LaravelSlotBooking\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BookingMigrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run the bookings migration
        $this->artisan('migrate', [
            '--path' => 'packages/my-packages/laravel-slot-booking/database/migrations',
            '--realpath' => true,
        ])->run();
    }

    /** @test */
    public function bookings_table_exists()
    {
        $tableName = config('slot-booking.tables.bookings', 'bookings');
        $this->assertTrue(Schema::hasTable($tableName), "The table '$tableName' does not exist.");
    }

    /** @test */
    public function bookings_table_has_expected_columns()
    {
        $tableName = config('slot-booking.tables.bookings', 'bookings');
        $expectedColumns = [
            'id', 'slot_id', 'bookable_id', 'bookable_type', 'created_at', 'updated_at'
        ];

        foreach ($expectedColumns as $column) {
            $this->assertTrue(Schema::hasColumn($tableName, $column), "Missing column: '$column'.");
        }
    }

    /** @test */
    public function bookings_table_has_unique_index_on_slot_id()
    {
        $tableName = config('slot-booking.tables.bookings', 'bookings');
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('$tableName')");
            $uniqueIndexFound = false;

            foreach ($indexes as $index) {
                if ($index->unique) {
                    $indexColumns = DB::select("PRAGMA index_info('{$index->name}')");
                    foreach ($indexColumns as $col) {
                        if ($col->name === 'slot_id') {
                            $uniqueIndexFound = true;
                            break 2;
                        }
                    }
                }
            }

            $this->assertTrue($uniqueIndexFound, "No unique index found on the 'slot_id' column.");
        } else {
            // For MySQL, PostgreSQL, etc.
            $uniqueIndexes = DB::select("
                SELECT INDEX_NAME, NON_UNIQUE 
                FROM INFORMATION_SCHEMA.STATISTICS 
                WHERE TABLE_NAME = ? 
                AND COLUMN_NAME = 'slot_id'
            ", [$tableName]);

            $foundUnique = false;
            foreach ($uniqueIndexes as $index) {
                if ($index->NON_UNIQUE == 0) {
                    $foundUnique = true;
                    break;
                }
            }

            $this->assertTrue($foundUnique, "No unique index found on the 'slot_id' column.");
        }
    }

    /** @test */
    public function bookings_table_has_foreign_key_to_slots_table()
    {
        $tableName = config('slot-booking.tables.bookings', 'bookings');
        $slotsTable = config('slot-booking.tables.slots', 'slots');

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            // Check foreign keys in SQLite
            $foreignKeys = DB::select("PRAGMA foreign_key_list('$tableName')");
            $hasForeignKey = false;
            foreach ($foreignKeys as $fk) {
                if ($fk->table === $slotsTable && $fk->from === 'slot_id' && $fk->to === 'id') {
                    $hasForeignKey = true;
                    break;
                }
            }
            $this->assertTrue($hasForeignKey, "No foreign key constraint found on 'slot_id' referencing '$slotsTable(id)'.");
        } else {
            // For MySQL, PostgreSQL, etc.
            $foreignKeys = DB::select("
                SELECT
                    CONSTRAINT_NAME, TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE TABLE_NAME = ? AND COLUMN_NAME = 'slot_id'
            ", [$tableName]);

            $hasForeignKey = false;
            foreach ($foreignKeys as $fk) {
                if ($fk->REFERENCED_TABLE_NAME === $slotsTable && $fk->REFERENCED_COLUMN_NAME === 'id') {
                    $hasForeignKey = true;
                    break;
                }
            }

            $this->assertTrue($hasForeignKey, "No foreign key constraint found on 'slot_id' referencing '$slotsTable(id)'.");
        }
    }
}
