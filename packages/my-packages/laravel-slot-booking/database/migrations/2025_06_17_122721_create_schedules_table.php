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
        $tableName = Config::get('slot-booking.tables.schedules', 'schedules');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->comment('ID of the service provider this schedule belongs to');
            $table->string('day_of_week')->comment('Day of the week (e.g., Monday, Tuesday, Every_Day, Weekdays, Weekends)');
            $table->time('start_time_daily')->comment('Daily start time for the schedule (HH:MM:SS)');
            $table->time('end_time_daily')->comment('Daily end time for the schedule (HH:MM:SS)');
            $table->integer('slot_duration')->nullable()->comment('Override default slot duration in minutes for this schedule');
            $table->integer('buffer_time')->nullable()->comment('Override default buffer time in minutes for this schedule');
            $table->string('timezone')->nullable()->comment('Override default timezone for this schedule (e.g., Asia/Riyadh)');
            $table->date('start_date')->nullable()->comment('Optional: Date from which this schedule is active');
            $table->date('end_date')->nullable()->comment('Optional: Date until which this schedule is active');
            $table->timestamps();

            $table->index(['service_id', 'day_of_week']);
            $table->unique(['service_id', 'day_of_week', 'start_time_daily', 'end_time_daily', 'start_date', 'end_date'], 'schedule_unique_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Config::get('slot-booking.tables.schedules', 'schedules');
        Schema::dropIfExists($tableName);
    }
};
