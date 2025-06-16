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
        $tableName = Config::get('slot-booking.tables.slots', 'slots');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_id')->comment('ID of the service provider or entity (e.g., doctor, taxi driver)');
            $table->dateTime('start_time');
            $table->dateTime('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['service_id', 'start_time', 'end_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = Config::get('slot-booking.tables.slots', 'slots');
        Schema::dropIfExists($tableName);
        }
};
