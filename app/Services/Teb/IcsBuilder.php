<?php
// app/Services/Teb/IcsBuilder.php

namespace App\Services\Teb;

use App\Models\Teb\TebReview;

// Calendar invite for "Schedule TEB Review" (slide 3).
class IcsBuilder
{
    public static function forReview(TebReview $review, ?string $organizerEmail = null): string
    {
        $p     = $review->project;
        $start = $review->review_at->copy()->utc();
        $end   = $start->copy()->addMinutes((int) $review->duration_minutes ?: 60);

        $summary = 'TEB Review: ' . ($p->teb_no ? "{$p->teb_no} " : '') . $p->title;
        $desc    = implode("\n", array_filter([
            "TEB Change Title: {$p->title}",
            "Category: {$p->category_label}",
            "Customer: {$p->customer_name}",
            "Project Owner: {$p->owner_name} ({$p->owner_department})",
            "Classification: {$p->classification_label}",
            "Type of Change: {$p->types_of_change_label}",
            "Affected Areas: {$p->affected_areas_label}",
            '',
            "Project Description: {$p->project_description}",
            $review->notes ? "Notes: {$review->notes}" : null,
            '',
            route('teb.projects.show', $p),
        ], fn($l) => $l !== null));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//TEB Portal//EN',
            // PUBLISH, not REQUEST: Exchange Online turns a REQUEST into a meeting and takes the
            // recipients from ATTENDEE lines, rejecting the mail with "contains no recipients".
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:teb-review-' . $review->id . '@' . parse_url(config('app.url'), PHP_URL_HOST),
            'DTSTAMP:' . now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:' . $start->format('Ymd\THis\Z'),
            'DTEND:' . $end->format('Ymd\THis\Z'),
            'SUMMARY:' . static::escape($summary),
            'LOCATION:' . static::escape((string) $review->location),
            'DESCRIPTION:' . static::escape($desc),
        ];

        if ($organizerEmail) {
            $lines[] = 'ORGANIZER:mailto:' . $organizerEmail;
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    protected static function escape(string $s): string
    {
        return str_replace(["\\", ';', ',', "\r\n", "\n"], ["\\\\", '\;', '\,', '\n', '\n'], $s);
    }
}
