<?php

namespace Khadija\LaravelSlotBooking\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class Schedule extends Model
{
    use HasFactory;

    protected $guarded = [];

    /**
     * Get the table associated with the model.
     */
    public function getTable()
    {
        return Config::get('slot-booking.tables.schedules', parent::getTable());
    }

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'start_time_daily' => 'string', // يتم تخزينها كسلسلة Time
        'end_time_daily' => 'string',   // يتم تخزينها كسلسلة Time
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // يمكنك إضافة علاقات أو توابع مساعدة هنا لاحقًا
}