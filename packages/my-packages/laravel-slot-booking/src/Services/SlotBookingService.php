<?php

namespace Khadija\LaravelSlotBooking\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Khadija\LaravelSlotBooking\Models\Slot; // سنحتاجها لاحقاً للحفظ في DB

class SlotBookingService
{
    /**
     * Generates a collection of available slots for a given service and time range.
     *
     * @param int $serviceId The ID of the service provider/entity.
     * @param Carbon $startTime The start time for slot generation.
     * @param Carbon $endTime The end time for slot generation.
     * @return Collection
     */
    public function generateAvailableSlots(int $serviceId, Carbon $startTime, Carbon $endTime): Collection
    {
        // احصل على المنطقة الزمنية المحددة في إعدادات الباكج أو استخدم الافتراضية
        $timezone = Config::get('slot-booking.timezone');

        // إذا ما تم تحديده، نستخدم المنطقة الزمنية الافتراضية من Laravel
        if (empty($timezone)) {
            $timezone = config('app.timezone');
        }

        // نعين المنطقة الزمنية إذا كانت غير فارغة
        if (!empty($timezone)) {
            $startTime = $startTime->setTimezone($timezone);
            $endTime = $endTime->setTimezone($timezone);
        }

        // تحقق من أن وقت البدء قبل وقت الانتهاء
        if ($startTime->greaterThanOrEqualTo($endTime)) {
            return collect(); // أرجع مجموعة فارغة إذا كانت المدة غير صالحة
        }

        $slots = new Collection();
        $slotDuration = Config::get('slot-booking.default_slot_duration');
        $bufferTime = Config::get('slot-booking.default_buffer_time');

        // ابدأ من وقت البدء المحدد
        $currentSlotStart = $startTime->copy();

        while ($currentSlotStart->lessThan($endTime)) {
            $currentSlotEnd = $currentSlotStart->copy()->addMinutes($slotDuration);

            // تأكد من أن نهاية الفترة لا تتجاوز وقت النهاية المحدد
            if ($currentSlotEnd->greaterThan($endTime)) {
                break; // توقف إذا كانت الفترة تتجاوز وقت النهاية
            }

            $slots->push([
                'service_id' => $serviceId,
                'start_time' => $currentSlotStart->copy(), // استخدم نسخة لتجنب التغيير بالمرجع
                'end_time' => $currentSlotEnd->copy(),
                'is_available' => true, // افتراضياً، الفترات المولدة متاحة
            ]);

            // انتقل إلى بداية الفترة التالية، مع الأخذ في الاعتبار وقت البافر
            $currentSlotStart = $currentSlotEnd->copy()->addMinutes($bufferTime);
        }

        return $slots;
    }

    // توابع أخرى ستضاف لاحقاً هنا، مثل bookSlot، cancelSlot، إلخ.
}