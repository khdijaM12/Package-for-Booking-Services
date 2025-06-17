<?php

namespace Khadija\LaravelSlotBooking\Tests\Feature;

use Khadija\LaravelSlotBooking\Models\Slot;
use Khadija\LaravelSlotBooking\Models\Booking;
use Orchestra\Testbench\Factories\UserFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Testing\TestResponse; // لاستخدامها في التحقق من الردود
use Khadija\LaravelSlotBooking\Tests\TestCase;

class SlotApiTest extends TestCase
{
    /** @test */
    public function it_returns_available_slots_via_api()
    {
        // إعدادات لضمان توليد فترات يمكننا اختبارها
        Config::set('slot-booking.default_slot_duration', 60); // 60 دقيقة
        Config::set('slot-booking.default_buffer_time', 0);    // لا بافر

        // إنشاء بعض الفترات التجريبية (يمكن أن يتم توليدها بواسطة Service)
        Slot::create([
            'service_id' => 1,
            'start_time' => Carbon::now()->startOfDay()->addHours(9), // 9:00 AM
            'end_time' => Carbon::now()->startOfDay()->addHours(10),   // 10:00 AM
            'is_available' => true,
        ]);
        Slot::create([
            'service_id' => 1,
            'start_time' => Carbon::now()->startOfDay()->addHours(10), // 10:00 AM
            'end_time' => Carbon::now()->startOfDay()->addHours(11),   // 11:00 AM
            'is_available' => false, // هذا Slot غير متاح
        ]);
        Slot::create([
            'service_id' => 2, // خدمة أخرى
            'start_time' => Carbon::now()->startOfDay()->addHours(9), // 9:00 AM
            'end_time' => Carbon::now()->startOfDay()->addHours(10),   // 10:00 AM
            'is_available' => true,
        ]);

        // إجراء طلب GET لـ API الفترات المتاحة
        $response = $this->getJson('/api/slots/available?service_id=1&date=' . Carbon::now()->toDateString());

        $response->assertStatus(200)
                 ->assertJsonStructure([ // التحقق من هيكل الـ JSON
                     'data' => [
                         '*' => ['id', 'start_time', 'end_time', 'is_available', 'service_id']
                     ]
                 ])
                 ->assertJsonCount(1, 'data'); // نتوقع فترة واحدة فقط (التي معرف خدمتها 1 ومتاحة)

        // التأكد من تفاصيل الفترة
        $response->assertJsonFragment([
            'service_id' => 1,
            'is_available' => true,
            'start_time' => Carbon::now()->startOfDay()->addHours(9)->toISOString(),
            'end_time' => Carbon::now()->startOfDay()->addHours(10)->toISOString(),
        ]);

    }

    /** @test */
    public function it_can_book_a_slot_via_api()
    {
        // إنشاء مستخدم للقيام بالحجز
        $user = UserFactory::new()->create();
        $this->actingAs($user); // تسجيل الدخول كـ user (للاختبار)

        // إنشاء Slot متاح للحجز
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => Carbon::now()->addDay()->startOfDay()->addHours(14), // غدًا 2 مساءً
            'end_time' => Carbon::now()->addDay()->startOfDay()->addHours(14)->addMinutes(30),
            'is_available' => true,
        ]);

        // التأكد من عدم وجود حجوزات قبل الاختبار
        $this->assertCount(0, Booking::all());
        $this->assertTrue($slot->is_available);

        // إجراء طلب POST لحجز Slot
        $response = $this->postJson('/api/slots/book', [
            'slot_id' => $slot->id,
        ]);

        $response->assertStatus(201) // 201 Created
                 ->assertJsonStructure([
                     'message',
                     'booking' => ['id', 'slot_id', 'bookable_id', 'bookable_type']
                 ])
                 ->assertJsonFragment([
                     'message' => 'Slot booked successfully!',
                     'slot_id' => $slot->id,
                     'bookable_id' => $user->id,
                     'bookable_type' => get_class($user),
                 ]);

        // التأكد من إنشاء الحجز في قاعدة البيانات
        $this->assertCount(1, Booking::all());
        $this->assertFalse($slot->fresh()->is_available); // Slot أصبح غير متاح
    }

    /** @test */
    public function it_returns_error_if_slot_is_not_available_when_booking_via_api()
    {
        $user = UserFactory::new()->create();
        $this->actingAs($user);

        // إنشاء Slot غير متاح
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => Carbon::now()->addDay()->startOfDay()->addHours(15),
            'end_time' => Carbon::now()->addDay()->startOfDay()->addHours(15)->addMinutes(30),
            'is_available' => false, // غير متاح
        ]);

        $response = $this->postJson('/api/slots/book', [
            'slot_id' => $slot->id,
        ]);

        $response->assertStatus(400) // 400 Bad Request
                 ->assertJson([
                     'message' => 'The requested slot is not available for booking.',
                 ]);

        $this->assertCount(0, Booking::all()); // لا يوجد حجز جديد
    }

    /** @test */
    public function it_returns_error_if_slot_is_already_booked_when_booking_via_api()
    {
        $user1 = UserFactory::new()->create();
        $user2 = UserFactory::new()->create();
        $this->actingAs($user1);

        // إنشاء Slot متاح
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => Carbon::now()->addDay()->startOfDay()->addHours(16),
            'end_time' => Carbon::now()->addDay()->startOfDay()->addHours(16)->addMinutes(30),
            'is_available' => true,
        ]);

        // المستخدم الأول يحجز Slot
        app('slot-booking')->bookSlot($slot->id, $user1);
        $this->assertFalse($slot->fresh()->is_available);
        $this->assertCount(1, Booking::all());

        // المستخدم الثاني يحاول حجز نفس الـ Slot
        $this->actingAs($user2);
        $response = $this->postJson('/api/slots/book', [
            'slot_id' => $slot->id,
        ]);

        $response->assertStatus(400) // 400 Bad Request
                 ->assertJson([
                     'message' => 'The requested slot has already been booked.',
                 ]);

        $this->assertCount(1, Booking::all()); // لا يوجد حجز جديد
    }

    /** @test */
    public function booking_requires_authentication()
    {
        // إنشاء Slot
        $slot = Slot::create([
            'service_id' => 1,
            'start_time' => Carbon::now()->addDay()->startOfDay()->addHours(17),
            'end_time' => Carbon::now()->addDay()->startOfDay()->addHours(17)->addMinutes(30),
            'is_available' => true,
        ]);

        // محاولة الحجز بدون تسجيل دخول
        $response = $this->postJson('/api/slots/book', [
            'slot_id' => $slot->id,
        ]);

        $response->assertStatus(401); // 401 Unauthorized

        $this->assertCount(0, Booking::all()); // لا يوجد حجز
    }
}