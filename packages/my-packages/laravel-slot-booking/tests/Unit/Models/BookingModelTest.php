<?php

// namespace Khadija\LaravelSlotBooking\Tests\Unit\Models;

// use Khadija\LaravelSlotBooking\Tests\TestCase;
// use Khadija\LaravelSlotBooking\Models\Slot;
// use Khadija\LaravelSlotBooking\Models\Booking;
// use Illuminate\Foundation\Testing\RefreshDatabase;
// use Orchestra\Testbench\Factories\UserFactory; 
// use Illuminate\Support\Facades\Artisan;

// class BookingModelTest extends TestCase
// {
//     use RefreshDatabase;

//         protected function setUp(): void
//     {
//         parent::setUp();

//         // تشغيل مهاجرات جدول users (إن لم تكن موجودة ضمن المهاجرات التلقائية)
//         Artisan::call('migrate', [
//             '--path' => 'database/migrations/2014_10_12_000000_create_users_table.php',
//         ]);

//         // تشغيل مهاجرات الباكدج الخاص بك أيضاً إذا لم تُشغل تلقائياً
//         Artisan::call('migrate', [
//             '--path' => 'packages/my-packages/laravel-slot-booking/database/migrations',
//         ]);
//     }

//     /** @test */
//     public function a_booking_can_be_created()
//     {
//         // التأكد من أن جدول bookings فارغ في البداية
//         $this->assertCount(0, Booking::all());

//         // إنشاء Slot أولاً، لأن الحجز يرتبط به
//         $slot = Slot::create([
//             'service_id' => 1,
//             'start_time' => now()->startOfDay()->addHours(10),
//             'end_time' => now()->startOfDay()->addHours(10)->addMinutes(30),
//             'is_available' => true,
//         ]);

//         // إنشاء مستخدم افتراضي للحجز (يمكن أن يكون أي كيان آخر)
//         $user = UserFactory::new()->create();

//         // إنشاء Booking جديد
//         $booking = Booking::create([
//             'slot_id' => $slot->id,
//             'bookable_id' => $user->id,
//             'bookable_type' => get_class($user), // تأكد من الحصول على اسم الكلاس الكامل
//         ]);

//         // التأكد من أن Booking تم إنشاؤه بنجاح
//         $this->assertCount(1, Booking::all());
//         $this->assertEquals($slot->id, $booking->slot_id);
//         $this->assertEquals($user->id, $booking->bookable_id);
//         $this->assertEquals(get_class($user), $booking->bookable_type);

//         // التأكد من أن العلاقة تعمل بشكل صحيح
//         $this->assertTrue($booking->slot->is($slot));
//         $this->assertTrue($booking->bookable->is($user));
//     }

//     /** @test */
//     public function a_booking_cannot_be_created_for_an_already_booked_slot()
//     {
//         // إنشاء Slot أولاً
//         $slot = Slot::create([
//             'service_id' => 1,
//             'start_time' => now()->startOfDay()->addHours(11),
//             'end_time' => now()->startOfDay()->addHours(11)->addMinutes(30),
//             'is_available' => true,
//         ]);

//         // إنشاء مستخدمين افتراضيين
//         $user1 = UserFactory::new()->create();
//         $user2 = UserFactory::new()->create();

//         // حجز الـ Slot من قبل المستخدم الأول
//         Booking::create([
//             'slot_id' => $slot->id,
//             'bookable_id' => $user1->id,
//             'bookable_type' => get_class($user1),
//         ]);

//         // محاولة حجز نفس الـ Slot من قبل المستخدم الثاني (يجب أن تفشل بسبب unique constraint)
//         $this->expectException(\Illuminate\Database\QueryException::class); // نتوقع حدوث QueryException
//         Booking::create([
//             'slot_id' => $slot->id,
//             'bookable_id' => $user2->id,
//             'bookable_type' => get_class($user2),
//         ]);
//     }
// }