<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * When a payment is recorded. Blank or today uses the current time. An earlier day is stored at
 * noon, so it sorts with that day and keeps the same date in every timezone.
 */
final class PaymentDate
{
    public static function resolve(?string $raw, ?Carbon $earliest = null): Carbon
    {
        $timezone = config('app.timezone');
        $raw = trim((string) $raw);
        $now = now($timezone);

        if ($raw === '') {
            return $now;
        }

        $day = Carbon::parse($raw, $timezone)->startOfDay();
        $today = $now->copy()->startOfDay();

        if ($day->gt($today)) {
            throw ValidationException::withMessages([
                'paid_on' => 'Paid date cannot be in the future.',
            ]);
        }

        if ($earliest !== null && $day->lt($earliest->copy()->startOfDay())) {
            throw ValidationException::withMessages([
                'paid_on' => 'Paid date cannot be before '.$earliest->format('d-m-Y').'.',
            ]);
        }

        if ($day->equalTo($today)) {
            return $now;
        }

        return $day->setTime(12, 0);
    }
}
