<?php

return [
    /*
     * Default Slot Duration in minutes
     * (e.g., 30 minutes for a typical doctor's appointment)
     */
    'default_slot_duration' => 30, // دقائق

    /*
     * Default Buffer Time between slots in minutes
     * (e.g., 15 minutes for cleanup or preparation)
     */
    'default_buffer_time' => 15, // دقائق

    /*
     * Table names for database migrations
     */
    'tables' => [
        'slots' => 'slots',
        'bookings' => 'bookings',
    ],

    /*
     * Timezone to use for calculating available slots
     * If null, application's default timezone will be used
     */
    'timezone' => null, // مثال: 'Asia/Riyadh' أو 'Africa/Cairo'
];
