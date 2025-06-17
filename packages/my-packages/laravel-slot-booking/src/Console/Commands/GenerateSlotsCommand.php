<?php

namespace Khadija\LaravelSlotBooking\Console\Commands;

use Illuminate\Console\Command;
use Khadija\LaravelSlotBooking\Services\SlotBookingService;
use Illuminate\Support\Carbon;

class GenerateSlotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slot-booking:generate-slots
                            {serviceId : The ID of the service provider/entity.}
                            {date : The date for which to generate slots (YYYY-MM-DD).}
                            {--start-time=09:00 : The daily start time for slot generation (HH:MM).}
                            {--end-time=17:00 : The daily end time for slot generation (HH:MM).}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and save available slots for a given service and date.';

    protected $slotBookingService;

    /**
     * Create a new command instance.
     *
     * @param SlotBookingService $slotBookingService
     * @return void
     */
    public function __construct(SlotBookingService $slotBookingService)
    {
        parent::__construct();
        $this->slotBookingService = $slotBookingService;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $serviceId = $this->argument('serviceId');
        $date = $this->argument('date');
        $startTimeStr = $this->option('start-time');
        $endTimeStr = $this->option('end-time');

        try {
            $startDateTime = Carbon::parse($date . ' ' . $startTimeStr);
            $endDateTime = Carbon::parse($date . ' ' . $endTimeStr);

            $this->info("Generating slots for Service ID: {$serviceId} on {$date} from {$startTimeStr} to {$endTimeStr}...");

            $generatedSlots = $this->slotBookingService->generateAvailableSlots(
                $serviceId,
                $startDateTime,
                $endDateTime
            );

            if ($generatedSlots->isEmpty()) {
                $this->warn("No slots generated for Service ID: {$serviceId} on {$date} with the given time range. Check your dates and times.");
                return Command::SUCCESS;
            }

            $saved = $this->slotBookingService->saveSlots($generatedSlots);

            if ($saved) {
                $this->info("Successfully generated and saved " . $generatedSlots->count() . " slots.");
                // يمكننا عرض الفترات المولدة للحظة
                foreach ($generatedSlots as $slot) {
                    $this->line(" - Slot ID {$slot['service_id']}: {$slot['start_time']} to {$slot['end_time']}");
                }
            } else {
                $this->error("Failed to save generated slots.");
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            \Log::error('GenerateSlotsCommand failed: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}