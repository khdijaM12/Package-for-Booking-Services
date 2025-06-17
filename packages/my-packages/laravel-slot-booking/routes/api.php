<?php

use Illuminate\Support\Facades\Route;
use Khadija\LaravelSlotBooking\Http\Controllers\SlotApiController;

Route::prefix('api')
    ->middleware('api') // تطبيق middleware 'api' الافتراضي
    ->group(function () {
        // مسار جلب الفترات المتاحة
        Route::get('slots/available', [SlotApiController::class, 'getAvailableSlots']);

        // مسار حجز الفترة (يتطلب مصادقة)
        Route::post('slots/book', [SlotApiController::class, 'bookSlot'])
             ->middleware('auth:sanctum'); // أو 'auth:api' حسب إعدادات مشروعك
    });