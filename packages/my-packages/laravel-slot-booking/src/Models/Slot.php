<?php

namespace Khadija\LaravelSlotBooking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Slot extends Model
{
    use HasFactory;

    // السماح بتعبئة كل الحقول (خطر فقط إذا كنتِ تقبلي بيانات من المستخدم بدون تحقق)
    protected $guarded = [];

    /**
     * Override table name from config.
     */
    public function getTable()
    {
        return Config::get('slot-booking.tables.slots', parent::getTable());
    }

    /**
     * Cast attributes to native types.
     */
    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_available' => 'boolean',
    ];

    // بإمكانكِ إضافة علاقات Models هنا لاحقاً
}
