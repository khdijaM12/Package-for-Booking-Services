<?php

namespace Khadija\LaravelSlotBooking\Tests\Unit;

use Khadija\LaravelSlotBooking\Tests\TestCase;
use Khadija\LaravelSlotBooking\Models\Slot; 
use Illuminate\Foundation\Testing\RefreshDatabase; 

class SlotModelTest extends TestCase
{
    use RefreshDatabase; 

    /** @test */
    public function a_slot_can_be_created()
    {
        // التأكد من أن جدول slots فارغ في البداية
        $this->assertCount(0, Slot::all());

        // إنشاء Slot جديد
        $slot = Slot::create([
            'service_id' => 1, // معرف الخدمة (مثلاً، طبيب بمعرف 1)
            'start_time' => now()->startOfDay()->addHours(9), // 9:00 صباحاً اليوم
            'end_time' => now()->startOfDay()->addHours(9)->addMinutes(30), // 9:30 صباحاً اليوم
            'is_available' => true,
        ]);

        // التأكد من أن Slot تم إنشاؤه بنجاح
        $this->assertCount(1, Slot::all());
        $this->assertEquals(1, $slot->service_id);
        $this->assertTrue($slot->is_available);
    }
}