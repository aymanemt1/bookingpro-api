<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Service;
use Carbon\Carbon;

/**
 * Generates bookable time slots for a service on a given date.
 *
 * Business hours: Monday–Saturday, 09:00–18:00. Closed on Sundays.
 * Slots start every 30 minutes; each slot lasts the service duration.
 */
class AvailabilityService
{
    public const OPEN_HOUR = 9;
    public const CLOSE_HOUR = 18;
    public const STEP_MINUTES = 30;

    public function isOpenDate(Carbon $date): bool
    {
        return ! $date->isSunday();
    }

    /**
     * @return array<int, array{start: string, end: string, available: bool}>
     */
    public function slots(Service $service, string $date): array
    {
        $day = Carbon::parse($date)->startOfDay();

        if (! $this->isOpenDate($day) || $day->isPast()) {
            return [];
        }

        $booked = Booking::where('service_id', $service->id)
            ->whereDate('date', $day->toDateString())
            ->active()
            ->get(['start_time', 'end_time']);

        $slots = [];
        $cursor = $day->copy()->setTime(self::OPEN_HOUR, 0);
        $closing = $day->copy()->setTime(self::CLOSE_HOUR, 0);

        while ($cursor->copy()->addMinutes($service->duration_minutes)->lte($closing)) {
            $start = $cursor->copy();
            $end = $cursor->copy()->addMinutes($service->duration_minutes);

            $overlaps = $booked->contains(
                fn ($b) => $start->format('H:i:s') < $b->end_time
                    && $end->format('H:i:s') > $b->start_time
            );

            // Hide slots that already started today
            $inPast = $day->isToday() && $start->lt(now());

            $slots[] = [
                'start' => $start->format('H:i'),
                'end' => $end->format('H:i'),
                'available' => ! $overlaps && ! $inPast,
            ];

            $cursor->addMinutes(self::STEP_MINUTES);
        }

        return $slots;
    }

    public function isSlotFree(Service $service, string $date, string $startTime): bool
    {
        foreach ($this->slots($service, $date) as $slot) {
            if ($slot['start'] === $startTime) {
                return $slot['available'];
            }
        }

        return false;
    }
}
