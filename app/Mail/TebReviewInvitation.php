<?php
// app/Mail/TebReviewInvitation.php

namespace App\Mail;

use App\Models\Teb\TebReview;
use App\Services\Teb\IcsBuilder;
use Illuminate\Mail\Attachment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TebReviewInvitation extends Mailable
{
    public function __construct(public TebReview $review)
    {
    }

    public function envelope(): Envelope
    {
        $p = $this->review->project;

        return new Envelope(subject: 'TEB Review: '.($p->teb_no ? "{$p->teb_no} " : '').$p->title);
    }

    public function content(): Content
    {
        return new Content(view: 'teb.mail.review-invitation', with: ['review' => $this->review, 'p' => $this->review->project]);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => IcsBuilder::forReview($this->review), 'teb-review.ics')
                ->withMime('text/calendar; method=REQUEST'),
        ];
    }
}
