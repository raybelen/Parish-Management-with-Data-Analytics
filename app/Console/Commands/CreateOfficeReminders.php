<?php

namespace App\Console\Commands;

use App\Support\AnalyticsData;
use App\Support\OfficeAudit;
use Illuminate\Console\Command;

class CreateOfficeReminders extends Command
{
    protected $signature = 'office:reminders';

    protected $description = 'Create daily office reminders without sending external messages';

    public function handle(AnalyticsData $analytics): int
    {
        foreach ($analytics->appointments()->whereIn('status', ['pending', 'confirmed', 'completed'])->with('payments')->lazyById(250) as $appointment) {
            $events = [];
            if ($appointment->status === 'confirmed' && $appointment->starts_at?->between(now(), now()->addDays(2))) {
                $events['upcoming'] = 'Upcoming service';
            }
            if ($appointment->status === 'pending' && ! $appointment->physicalDocumentsComplete()) {
                $events['documents'] = 'Physical documents awaiting verification';
            }
            if ($appointment->balanceCents() > 0) {
                $events['balance'] = 'Outstanding payment balance';
            }
            foreach ($events as $event => $message) {
                OfficeAudit::notify($appointment, $event, $message.': '.$appointment->reference_number, $appointment->id.':'.$event.':'.now()->toDateString());
            }
        }
        $this->info('Office reminders updated.');

        return self::SUCCESS;
    }
}
