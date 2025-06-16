<?php

namespace Khadija\LaravelSlotBooking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Booking extends Model
{
    use HasFactory;

    protected $guarded = []; // السماح بتعبئة جميع الحقول بشكل جماعي

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        // استخدام اسم الجدول من ملف الإعدادات الخاص بالباكج
        return Config::get('slot-booking.tables.bookings', parent::getTable());
    }

    /**
     * Get the owning bookable model.
     */
    public function bookable()
    {
        return $this->morphTo(); // علاقة متعددة الأشكال
    }

    /**
     * Get the slot that owns the booking.
     */
    public function slot()
    {
        return $this->belongsTo(Slot::class, 'slot_id');
    }
}