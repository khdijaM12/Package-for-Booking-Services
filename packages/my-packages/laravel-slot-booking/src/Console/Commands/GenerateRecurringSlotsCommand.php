<?php

namespace Khadija\LaravelSlotBooking\Console\Commands;

use Illuminate\Console\Command;
use Khadija\LaravelSlotBooking\Services\SlotBookingService;
use Illuminate\Support\Carbon;

class GenerateRecurringSlotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slot-booking:generate-recurring-slots
                            {serviceId : The ID of the service for which to generate slots.}
                            {--start-date=today : The start date for generation (YYYY-MM-DD).}
                            {--end-date= : The end date for generation (YYYY-MM-DD). Defaults to start-date + 7 days if not provided.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and save slots based on recurring schedules for a service over a date range.';

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
        $startDateStr = $this->option('start-date');
        $endDateStr = $this->option('end-date');

        try {
            $startDate = Carbon::parse($startDateStr);
            $endDate = $endDateStr ? Carbon::parse($endDateStr) : $startDate->copy()->addDays(7); // الافتراضي هو 7 أيام

            if ($startDate->greaterThan($endDate)) {
                $this->error("The start date cannot be after the end date.");
                return Command::FAILURE;
            }

            $this->info("Generating recurring slots for Service ID: {$serviceId} from {$startDate->toDateString()} to {$endDate->toDateString()}...");

            $savedSlots = $this->slotBookingService->generateAndSaveSlotsFromSchedule(
                $serviceId,
                $startDate,
                $endDate
            );

            if ($savedSlots->isEmpty()) {
                $this->warn("No new slots generated or saved for Service ID: {$serviceId} in the specified date range. Ensure schedules are defined.");
            } else {
                $this->info("Successfully generated and saved " . $savedSlots->count() . " new slots based on schedules.");
            }

        } catch (\Exception $e) {
            $this->error("An error occurred: " . $e->getMessage());
            \Log::error('GenerateRecurringSlotsCommand failed: ' . $e->getMessage(), ['exception' => $e]);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}