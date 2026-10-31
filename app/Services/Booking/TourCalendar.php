<?php

namespace App\Services\Booking;

use App\Models\TourRequest;

/**
 * A calendar invitation for a confirmed tour (FR-BK-2, RFC 5545): the time in UTC, the location's name
 * and address, and nothing about the visitor.
 */
class TourCalendar
{
    public function for(TourRequest $tour): string
    {
        $host = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'smartgro.space');
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//SmartGro//Tours//EN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.strtolower($tour->reference).'@'.$host,
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$tour->scheduled_start_at->copy()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$tour->scheduledEndAt()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->text('Tour of SmartGro '.$tour->property->name),
            'LOCATION:'.$this->text($tour->property->addressLine()),
            'DESCRIPTION:'.$this->text('Your SmartGro tour, reference '.$tour->reference.'.'),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", array_map($this->fold(...), $lines))."\r\n";
    }

    /**
     * Escapes a text value (RFC 5545 §3.3.11).
     */
    private function text(string $value): string
    {
        return str_replace(['\\', ';', ',', "\r\n", "\n"], ['\\\\', '\;', '\,', '\n', '\n'], $value);
    }

    /**
     * Folds lines longer than 75 octets (RFC 5545 §3.1).
     */
    private function fold(string $line): string
    {
        $parts = [''];

        foreach (mb_str_split($line) as $character) {
            if (strlen(end($parts).$character) > 74) {
                $parts[] = '';
            }

            $parts[array_key_last($parts)] .= $character;
        }

        return implode("\r\n ", $parts);
    }
}
