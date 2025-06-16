<?php

namespace Khadija\LaravelSlotBooking\Tests\Unit\Models;

use Khadija\LaravelSlotBooking\Models\Slot;
use Khadija\LaravelSlotBooking\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SlotModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_resolves_the_correct_table_name()
    {
        $slot = new Slot();
        $this->assertEquals(config('slot-booking.tables.slots', 'slots'), $slot->getTable());
    }

    /** @test */
    public function a_slot_can_be_created()
    {
        // تأكدي أن الجدول فاضي أولاً
        $this->assertCount(0, Slot::all());

        // إنشاء Slot
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => now()->startOfDay()->addHours(9),       // 9:00 صباحاً
            'end_time' => now()->startOfDay()->addHours(9)->addMinutes(30), // 9:30 صباحاً
            'is_available' => true,
        ]);

        // تحقق أنه اتسجل فعلاً
        $this->assertCount(1, Slot::all());
        $this->assertEquals(1, $slot->service_id);
        $this->assertTrue($slot->is_available);
    }
}
