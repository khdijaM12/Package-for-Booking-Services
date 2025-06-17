<?php

namespace Khadija\LaravelSlotBooking\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Khadija\LaravelSlotBooking\Models\Slot;
use Illuminate\Database\QueryException;
use Khadija\LaravelSlotBooking\Models\Booking;
use Khadija\LaravelSlotBooking\Models\Schedule; // أضف هذا السطر
use Khadija\LaravelSlotBooking\Exceptions\SlotNotAvailableException;
use Khadija\LaravelSlotBooking\Exceptions\SlotAlreadyBookedException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SlotBookingService
{
    protected $defaultSlotDuration;
    protected $defaultBufferTime;
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
     * Generates and saves slots based on recurring schedules for a given date range.
     *
     * @param int $serviceId The ID of the service.
     * @param Carbon $startDate The start date for generating slots.
     * @param Carbon $endDate The end date for generating slots.
     * @return Collection<int, Slot> Collection of saved Slot models.
     */
    public function generateAndSaveSlotsFromSchedule(int $serviceId, Carbon $startDate, Carbon $endDate): Collection
    {
        $savedSlots = collect();
        $currentDate = $startDate->copy();
        $timezoneToUse = $this->timezone ?: config('app.timezone');

        // جلب جميع الجداول المتكررة للخدمة المطلوبة
        $schedules = Schedule::where('service_id', $serviceId)
                             ->where(function ($query) use ($currentDate) {
                                 $query->whereNull('start_date')
                                       ->orWhere('start_date', '<=', $currentDate->toDateString());
                             })
                             ->where(function ($query) use ($currentDate) {
                                 $query->whereNull('end_date')
                                       ->orWhere('end_date', '>=', $currentDate->toDateString());
                             })
                             ->get();

        if ($schedules->isEmpty()) {
            return $savedSlots; // لا توجد جداول لهذه الخدمة
        }

        while ($currentDate->lessThanOrEqualTo($endDate)) {
            $dayName = $currentDate->format('l'); // مثل 'Monday'

            // تحقق من الجداول المتكررة لهذا اليوم
            foreach ($schedules as $schedule) {
                $appliesToDay = false;
                if ($schedule->day_of_week === 'Every_Day') {
                    $appliesToDay = true;
                } elseif ($schedule->day_of_week === 'Weekdays' && $currentDate->isWeekday()) {
                    $appliesToDay = true;
                } elseif ($schedule->day_of_week === 'Weekends' && $currentDate->isWeekend()) {
                    $appliesToDay = true;
                } elseif ($schedule->day_of_week === $dayName) {
                    $appliesToDay = true;
                }

                if ($appliesToDay) {
                    // تحديد أوقات البدء والانتهاء اليومية من الجدول
                    $dailyStartTime = Carbon::parse($currentDate->toDateString() . ' ' . $schedule->start_time_daily, $timezoneToUse);
                    $dailyEndTime = Carbon::parse($currentDate->toDateString() . ' ' . $schedule->end_time_daily, $timezoneToUse);

                    // إذا كان هناك تجاوز للمدة أو البافر أو المنطقة الزمنية في الجدول
                    $slotDuration = $schedule->slot_duration ?? $this->defaultSlotDuration;
                    $bufferTime = $schedule->buffer_time ?? $this->defaultBufferTime;
                    $scheduleTimezone = $schedule->timezone ?? $timezoneToUse;

                    // توليد الفترات لهذا اليوم من الجدول
                    $currentSlotStart = $dailyStartTime->copy()->setTimezone($scheduleTimezone);

                    while ($currentSlotStart->lessThan($dailyEndTime)) {
                        $currentSlotEnd = $currentSlotStart->copy()->addMinutes($slotDuration);

                        if ($currentSlotEnd->greaterThan($dailyEndTime)) {
                            break;
                        }

                        // تحقق مما إذا كان الـ slot موجودًا بالفعل في قاعدة البيانات لتجنب الازدواجية
                        $existingSlot = Slot::where('service_id', $serviceId)
                                            ->where('start_time', $currentSlotStart->toDateTimeString())
                                            ->where('end_time', $currentSlotEnd->toDateTimeString())
                                            ->first();

                        if (!$existingSlot) {
                            $slot = Slot::create([
                                'service_id' => $serviceId,
                                'start_time' => $currentSlotStart->copy(),
                                'end_time' => $currentSlotEnd->copy(),
                                'is_available' => true,
                            ]);
                            $savedSlots->push($slot);
                        }

                        $currentSlotStart = $currentSlotEnd->copy()->addMinutes($bufferTime);
                    }
                }
            }
            $currentDate->addDay(); // انتقل إلى اليوم التالي
        }

        return $savedSlots;
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
            // استخدام insertOrIgnore لتجنب الأخطاء إذا كانت الفترات موجودة بالفعل
            DB::table(Config::get('slot-booking.tables.slots', 'slots'))->insertOrIgnore($slotsToInsert);
            return true;
        } catch (\Exception $e) {
            \Log::error('Failed to save slots: ' . $e->getMessage());
            return false;
        }
    }

    public function bookSlot(int $slotId, Model $bookable): Booking
    {
        return DB::transaction(function () use ($slotId, $bookable) {
            $slot = Slot::where('id', $slotId)->lockForUpdate()->first();

            if (!$slot) {
                throw new SlotNotAvailableException();
            }

            if (Booking::where('slot_id', $slot->id)->exists()) {
                throw new SlotAlreadyBookedException();
            }

            if (!$slot->is_available) {
                throw new SlotNotAvailableException();
            }

            try {
                $booking = Booking::create([
                    'slot_id' => $slot->id,
                    'bookable_id' => $bookable->id,
                    'bookable_type' => get_class($bookable),
                ]);

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
