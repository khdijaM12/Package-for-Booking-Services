<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Config;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = Config::get('slot-booking.tables.bookings', 'bookings'); // اسم جدول الحجز
        $slotsTableName = Config::get('slot-booking.tables.slots', 'slots');  // اسم جدول الفترات

        Schema::create($tableName, function (Blueprint $table) use ($slotsTableName) {
            $table->id();
            $table->foreignId('slot_id')
                  ->constrained($slotsTableName) // ربط المفتاح الأجنبي بجدول slots
                  ->cascadeOnDelete(); // حذف الحجز عند حذف الفترة

            $table->unsignedBigInteger('bookable_id'); // ID الكيان الذي حجز (مثلاً مستخدم)
            $table->string('bookable_type'); // نوع الكيان الذي حجز (مثلاً App\Models\User)

            $table->timestamps();

            $table->unique('slot_id'); // منع الحجز المزدوج على نفس الفترة

            $table->index(['bookable_id', 'bookable_type']); // مؤشر لتسريع الاستعلامات حسب الكيان
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Config::get('slot-booking.tables.bookings', 'bookings');
        Schema::dropIfExists($tableName);  
    }
};
