<?php

namespace Khadija\LaravelSlotBooking\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Khadija\LaravelSlotBooking\Models\Slot; // سنحتاجها لاحقاً للحفظ في DB
use Illuminate\Database\QueryException;
use Khadija\LaravelSlotBooking\Models\Booking;
use Khadija\LaravelSlotBooking\Exceptions\SlotNotAvailableException;
use Khadija\LaravelSlotBooking\Exceptions\SlotAlreadyBookedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

        public function bookSlot(int $slotId, Model $bookable): Booking
    {
        return DB::transaction(function () use ($slotId, $bookable) {
            // 1. استرجاع الـ Slot وتأمينه لمنع التعارضات (Locking)
            // forUpdate() يضيف قفل صف (row lock) على السجل في قاعدة البيانات
            $slot = Slot::where('id', $slotId)->lockForUpdate()->first();

            // 1. تحقق من وجود الـ Slot
            if (!$slot) {
                throw new SlotNotAvailableException();
            }

            // 2. تحقق إذا كان محجوز بالفعل
            if (Booking::where('slot_id', $slot->id)->exists()) {
                throw new SlotAlreadyBookedException();
            }

            // 3. تحقق إذا كان غير متاح (لكن مش محجوز)
            if (!$slot->is_available) {
                throw new SlotNotAvailableException();
            }

            try {
                // 4. إنشاء الحجز
                $booking = Booking::create([
                    'slot_id' => $slot->id,
                    'bookable_id' => $bookable->id,
                    'bookable_type' => get_class($bookable),
                ]);

                // 5. تحديث حالة الـ Slot لجعله غير متاح
                // هذا ضروري لمنع حجوزات مستقبلية لنفس Slot من الظهور كمتاحة.
                // ولكن الحماية الأساسية للحجز المزدوج هي unique index على slot_id في جدول bookings
                $slot->update(['is_available' => false]);

                // هنا يمكننا إطلاق حدث (Event) لإرسال تأكيد الحجز مثلاً (سنضيفها لاحقاً)
                // event(new BookingConfirmed($booking));

                return $booking;

            } catch (QueryException $e) {
                // في حال حدوث QueryException بسبب unique constraint على slot_id
                // هذا يعني أن Slot قد تم حجزه في نفس اللحظة من قبل عملية أخرى (Race Condition)
                if (str_contains($e->getMessage(), 'unique constraint')) {
                    throw new SlotAlreadyBookedException();
                }
                throw $e; // أعد رمي أي استثناء آخر
            }
        });
    }

    // توابع أخرى ستضاف لاحقاً هنا، مثل bookSlot، cancelSlot، إلخ.
}