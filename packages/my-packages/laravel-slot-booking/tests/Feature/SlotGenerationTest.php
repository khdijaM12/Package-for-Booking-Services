<?php

namespace Khadija\LaravelSlotBooking\Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Khadija\LaravelSlotBooking\Tests\TestCase;

class SlotGenerationTest extends TestCase
{
    /** @test */
    public function it_can_generate_slots_for_a_given_period_and_service()
    {
        // قم بتعيين إعدادات افتراضية للباكج للاختبار
        Config::set('slot-booking.default_slot_duration', 30); // 30 دقيقة للفترة
        Config::set('slot-booking.default_buffer_time', 15); // 15 دقيقة فاصل

        $serviceId = 1;
        $startTime = Carbon::parse('2025-06-16 09:00:00'); // اليوم هو 16 يونيو 2025
        $endTime = Carbon::parse('2025-06-16 12:00:00');

        // نحن نتوقع أن تكون لدينا خدمة ستوفر طريقة لتوليد الفترات
        // هذا سيكون الكلاس الذي سنبنيه لاحقًا
        $slots = app('slot-booking')->generateAvailableSlots(
            $serviceId,
            $startTime,
            $endTime
        );

        // التحقق من عدد الفترات المتوقعة
        // من 9:00 إلى 12:00 (3 ساعات = 180 دقيقة)
        // كل فترة 30 دقيقة + 15 دقيقة بافر = 45 دقيقة لكل دورة
        // 180 / 45 = 4 فترات
        // 09:00-09:30 (بافر حتى 09:45)
        // 09:45-10:15 (بافر حتى 10:30) - لا، هذه ليست الطريقة الصحيحة لحساب البافر
        // البافر هو بعد كل حجز، وليس قبل كل حجز.
        // 09:00-09:30 (فاصل 09:30-09:45)
        // 09:45-10:15 (فاصل 10:15-10:30)
        // 10:30-11:00 (فاصل 11:00-11:15)
        // 11:15-11:45 (فاصل 11:45-12:00)
        // لذا، 4 فترات صحيحة.
        $this->assertCount(4, $slots);

        // التحقق من صحة الفترات الزمنية وتوقيتاتها
        $this->assertEquals('09:00:00', $slots[0]['start_time']->format('H:i:s'));
        $this->assertEquals('09:30:00', $slots[0]['end_time']->format('H:i:s'));

        $this->assertEquals('09:45:00', $slots[1]['start_time']->format('H:i:s'));
        $this->assertEquals('10:15:00', $slots[1]['end_time']->format('H:i:s'));

        $this->assertEquals('10:30:00', $slots[2]['start_time']->format('H:i:s'));
        $this->assertEquals('11:00:00', $slots[2]['end_time']->format('H:i:s'));

        $this->assertEquals('11:15:00', $slots[3]['start_time']->format('H:i:s'));
        $this->assertEquals('11:45:00', $slots[3]['end_time']->format('H:i:s'));

        // تأكد أن الخدمة الصحيحة مرتبطة بكل فترة
        foreach ($slots as $slot) {
            $this->assertEquals($serviceId, $slot['service_id']);
        }
    }

    /** @test */
    public function it_handles_different_timezones_for_slot_generation()
    {
        // قم بتعيين منطقة زمنية معينة للباكج للاختبار
        Config::set('slot-booking.timezone', 'America/New_York'); // التوقيت الشرقي للولايات المتحدة
        Config::set('slot-booking.default_slot_duration', 60); // 60 دقيقة
        Config::set('slot-booking.default_buffer_time', 0); // لا يوجد بافر

        $serviceId = 2;
        $startTime = Carbon::parse('2025-06-16 08:00:00', 'UTC'); // وقت البداية بتوقيت عالمي منسق (UTC)
        $endTime = Carbon::parse('2025-06-16 11:00:00', 'UTC'); // وقت الانتهاء بتوقيت عالمي منسق (UTC)

        // نفترض أن خدمة SlotBookingService ستكون مسجلة في Service Container
        $slots = app('slot-booking')->generateAvailableSlots(
            $serviceId,
            $startTime,
            $endTime
        );

        // نيويورك متأخرة 4 ساعات عن UTC في يونيو (Daylight Saving Time)
        // 8:00 UTC = 4:00 AM New York
        // 11:00 UTC = 7:00 AM New York
        // 3 ساعات في UTC (8-11) تعني 3 فترات كل منها ساعة واحدة
        // 8:00-9:00 UTC -> 4:00-5:00 AM NY
        // 9:00-10:00 UTC -> 5:00-6:00 AM NY
        // 10:00-11:00 UTC -> 6:00-7:00 AM NY
        $this->assertCount(3, $slots);
        $this->assertEquals('04:00:00', $slots[0]['start_time']->setTimezone('America/New_York')->format('H:i:s'));
        $this->assertEquals('05:00:00', $slots[0]['end_time']->setTimezone('America/New_York')->format('H:i:s'));
        $this->assertEquals('06:00:00', $slots[2]['start_time']->setTimezone('America/New_York')->format('H:i:s'));
        $this->assertEquals('07:00:00', $slots[2]['end_time']->setTimezone('America/New_York')->format('H:i:s'));

        // إعادة تعيين المنطقة الزمنية لضمان عدم تأثير الاختبارات اللاحقة
        Config::set('slot-booking.timezone', null);
    }

    /** @test */
    public function it_does_not_generate_slots_if_end_time_is_before_start_time()
    {
        Config::set('slot-booking.default_slot_duration', 30);
        Config::set('slot-booking.default_buffer_time', 0);

        $serviceId = 1;
        $startTime = Carbon::parse('2025-06-16 10:00:00');
        $endTime = Carbon::parse('2025-06-16 09:00:00'); // نهاية قبل البداية

        $slots = app('slot-booking')->generateAvailableSlots(
            $serviceId,
            $startTime,
            $endTime
        );

        $this->assertEmpty($slots); // نتوقع عدم توليد أي فترات
    }
}