<?php

namespace Khadija\LaravelSlotBooking\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Khadija\LaravelSlotBooking\Models\Slot;
use Illuminate\Database\QueryException;
use Khadija\LaravelSlotBooking\Models\Booking;
use Khadija\LaravelSlotBooking\Exceptions\SlotNotAvailableException;
use Khadija\LaravelSlotBooking\Exceptions\SlotAlreadyBookedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SlotBookingService
{
    /**
     * @var int Default slot duration in minutes.
     */
    protected $defaultSlotDuration;

    /**
     * @var int Default buffer time in minutes.
     */
    protected $defaultBufferTime;

    /**
     * @var string|null Timezone for slot calculations.
     */
    protected $timezone;

    public function __construct()
    {
        $this->defaultSlotDuration = Config::get('slot-booking.default_slot_duration', 30);
        $this->defaultBufferTime = Config::get('slot-booking.default_buffer_time', 15);
        $this->timezone = Config::get('slot-booking.timezone', config('app.timezone'));
    }

    /**
     * Generates a collection of available slots for a given service and time range.
     *
     * @param int $serviceId The ID of the service provider/entity.
     * @param Carbon $startTime The start time for slot generation.
     * @param Carbon $endTime The end time for slot generation.
     * @return Collection<int, array> A collection of slot data (not saved to DB yet).
     */
    public function generateAvailableSlots(int $serviceId, Carbon $startTime, Carbon $endTime): Collection
    {
        // إذا ما تم تحديده في الكونفيج، نستخدم المنطقة الزمنية الافتراضية من Laravel
        $timezoneToUse = $this->timezone ?: config('app.timezone');

        // نعين المنطقة الزمنية
        $startTime = $startTime->copy()->setTimezone($timezoneToUse);
        $endTime = $endTime->copy()->setTimezone($timezoneToUse);

        // تحقق من أن وقت البدء قبل وقت الانتهاء
        if ($startTime->greaterThanOrEqualTo($endTime)) {
            return collect(); // أرجع مجموعة فارغة إذا كانت المدة غير صالحة
        }

            $slots = new Collection();
            $currentSlotStart = $startTime->copy();

        while ($currentSlotStart->lessThan($endTime)) {
            $currentSlotEnd = $currentSlotStart->copy()->addMinutes($this->defaultSlotDuration);

            // تأكد من أن نهاية الفترة لا تتجاوز وقت النهاية المحدد
            if ($currentSlotEnd->greaterThan($endTime)) {
                break; // توقف إذا كانت الفترة تتجاوز وقت النهاية
            }

            $slots->push([
                'service_id' => $serviceId,
                'start_time' => $currentSlotStart->copy(),
                'end_time' => $currentSlotEnd->copy(),
                'is_available' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // انتقل إلى بداية الفترة التالية، مع الأخذ في الاعتبار وقت البافر
            $currentSlotStart = $currentSlotEnd->copy()->addMinutes($this->defaultBufferTime);
        }

        return $slots;
    }

    /**
     * Saves a collection of generated slots to the database.
     *
     * @param Collection<int, array> $slotsData The collection of slot data to save.
     * @return bool True if slots were saved successfully, false otherwise.
     */
    public function saveSlots(Collection $slotsData): bool
    {
        // يمكننا استخدام insert لعملية حفظ جماعية لتقليل عدد الاستعلامات
        // تأكدي أن كل عناصر الـ collection هي array وليست objects
        $slotsToInsert = $slotsData->map(function ($slot) {
            // تحويل Carbon instances إلى سلاسل DateTime للقاعدة البيانات
            $slot['start_time'] = $slot['start_time']->toDateTimeString();
            $slot['end_time'] = $slot['end_time']->toDateTimeString();
            return $slot;
        })->toArray();

        if (empty($slotsToInsert)) {
            return false;
        }

        try {
            DB::table(Config::get('slot-booking.tables.slots', 'slots'))->insert($slotsToInsert);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to save slots: ' . $e->getMessage());
            return false;
        }
    }

    public function bookSlot(int $slotId, Model $bookable): Booking
    {
        return DB::transaction(function () use ($slotId, $bookable) {
             // 1. استرجاع الـ Slot وتأمينه لمنع التعارضات (Locking)
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
                $slot->update(['is_available' => false]);

                return $booking;

                } catch (QueryException $e) {
                if (str_contains($e->getMessage(), 'unique constraint')) {
                    throw new SlotAlreadyBookedException();
                }
                throw $e;
            }
        });
    }

}