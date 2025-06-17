<?php

namespace Khadija\LaravelSlotBooking\Tests\Feature;

use Khadija\LaravelSlotBooking\Models\Slot;
use Khadija\LaravelSlotBooking\Models\Booking;
use Orchestra\Testbench\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Khadija\LaravelSlotBooking\Exceptions\SlotNotAvailableException; // سننشئ هذا الاستثناء لاحقاً
use Khadija\LaravelSlotBooking\Exceptions\SlotAlreadyBookedException; // سننشئ هذا الاستثناء لاحقاً
use Khadija\LaravelSlotBooking\Tests\TestCase;

class BookingManagementTest extends TestCase
{
    /** @test */
    public function a_slot_can_be_booked_successfully()
    {
        // إنشاء Slot متاح للحجز
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHour()->addMinutes(30),
            'is_available' => true,
        ]);

        // التأكد من عدم وجود حجوزات قبل الاختبار
        $this->assertCount(0, Booking::all());

        // إنشاء مستخدم افتراضي (الكيان الذي سيقوم بالحجز)
        $user = UserFactory::new()->create();

        // استدعاء خدمة الحجز
        // نفترض أن خدمة "slot-booking" لديها دالة bookSlot
        $booking = app('slot-booking')->bookSlot($slot->id, $user);

        // التأكد من إنشاء حجز واحد
        $this->assertCount(1, Booking::all());
        $this->assertNotNull($booking);
        $this->assertEquals($slot->id, $booking->slot_id);
        $this->assertEquals($user->id, $booking->bookable_id);
        $this->assertEquals(get_class($user), $booking->bookable_type);

        // التأكد من أن الـ slot لم يعد متاحاً (اختياري لكن جيد للمنطق)
        $this->assertFalse($slot->fresh()->is_available);
    }

    /** @test */
    public function a_slot_cannot_be_booked_if_it_is_not_available()
    {
        // إنشاء Slot غير متاح للحجز
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHour()->addMinutes(30),
            'is_available' => false, // غير متاح
        ]);

        $user = UserFactory::new()->create();

        // نتوقع أن يتم رمي استثناء SlotNotAvailableException
        $this->expectException(SlotNotAvailableException::class);

        app('slot-booking')->bookSlot($slot->id, $user);

        // التأكد من عدم إنشاء أي حجز
        $this->assertCount(0, Booking::all());
    }

    /** @test */
    public function a_slot_cannot_be_booked_if_it_is_already_booked()
    {
        // إنشاء Slot متاح
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => now()->addHour(),
            'end_time' => now()->addHour()->addMinutes(30),
            'is_available' => true,
        ]);

        // مستخدمان مختلفان
        $user1 = UserFactory::new()->create();
        $user2 = UserFactory::new()->create();

        // المستخدم الأول يحجز الـ slot
        app('slot-booking')->bookSlot($slot->id, $user1);

        // التأكد من أن الـ slot لم يعد متاحاً بعد الحجز الأول
        $this->assertFalse($slot->fresh()->is_available);
        $this->assertCount(1, Booking::all());

        // محاولة المستخدم الثاني حجز نفس الـ slot (يجب أن تفشل)
        $this->expectException(SlotAlreadyBookedException::class);

        app('slot-booking')->bookSlot($slot->id, $user2);

        // التأكد من عدم إنشاء حجز ثاني
        $this->assertCount(1, Booking::all());
    }
}