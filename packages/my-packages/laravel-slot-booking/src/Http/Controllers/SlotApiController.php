<?php

namespace Khadija\LaravelSlotBooking\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller; // Controller الأساسي
use Khadija\LaravelSlotBooking\Services\SlotBookingService;
use Khadija\LaravelSlotBooking\Models\Slot; // لاسترجاع الفترات المتاحة
use Khadija\LaravelSlotBooking\Exceptions\SlotNotAvailableException;
use Khadija\LaravelSlotBooking\Exceptions\SlotAlreadyBookedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator; // للتحقق من صحة البيانات المدخلة

class SlotApiController extends Controller
{
    protected $slotBookingService;

    public function __construct(SlotBookingService $slotBookingService)
    {
        $this->slotBookingService = $slotBookingService;
    }

    /**
     * Get available slots for a service on a given date.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableSlots(Request $request)
    {
        // التحقق من صحة البيانات المدخلة
        $validator = Validator::make($request->all(), [
            'service_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d',
            // يمكنك إضافة قواعد للحد الأدنى والأقصى للوقت هنا
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422); // 422 Unprocessable Entity
        }

        $serviceId = $request->input('service_id');
        $date = Carbon::parse($request->input('date'))->toDateString();

        // سنفترض هنا أننا نبحث عن الفترات المسجلة في قاعدة البيانات والمتاحة.
        // يمكنك دمج هذا لاحقًا مع منطق توليد الفترات الزمنية إذا كانت الخدمات لا تمتلك فترات ثابتة.
        $slots = Slot::where('service_id', $serviceId)
                     ->where('is_available', true)
                     ->whereDate('start_time', $date)
                     ->orderBy('start_time')
                     ->get();

        return response()->json([
            'message' => 'Available slots retrieved successfully.',
            'data' => $slots->map(function ($slot) {
                return [
                    'id' => $slot->id,
                    'service_id' => $slot->service_id,
                    'start_time' => $slot->start_time->toISOString(), // تحويل إلى ISO 8601
                    'end_time' => $slot->end_time->toISOString(),
                    'is_available' => $slot->is_available,
                ];
            }),
        ], 200);
    }

    /**
     * Book a specific slot.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function bookSlot(Request $request)
    {
        // التحقق من أن المستخدم مسجل الدخول
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'slot_id' => 'required|integer|exists:' . config('slot-booking.tables.slots') . ',id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $slotId = $request->input('slot_id');
        $bookable = auth()->user(); // الكيان الذي يقوم بالحجز هو المستخدم الحالي

        try {
            $booking = $this->slotBookingService->bookSlot($slotId, $bookable);

            // هنا يمكننا إطلاق حدث (Event) لإرسال تأكيد الحجز (سنضيفه في مرحلة لاحقة)
            // event(new BookingConfirmed($booking));

            return response()->json([
                'message' => 'Slot booked successfully!',
                'booking' => [
                    'id' => $booking->id,
                    'slot_id' => $booking->slot_id,
                    'bookable_id' => $booking->bookable_id,
                    'bookable_type' => $booking->bookable_type,
                ],
            ], 201); // 201 Created

        } catch (SlotNotAvailableException $e) {
            return response()->json(['message' => $e->getMessage()], 400); // 400 Bad Request
        } catch (SlotAlreadyBookedException $e) {
            return response()->json(['message' => $e->getMessage()], 400); // 400 Bad Request
        } catch (\Exception $e) {
            // التقاط أي أخطاء أخرى غير متوقعة
            \Log::error('Booking failed: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'An unexpected error occurred during booking.'], 500); // 500 Internal Server Error
        }
    }
}