<?php
// app/Mail/TebAdiNotification.php

namespace App\Mail;

use App\Models\Teb\TebProject;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

// TFP16-008 step 6: notify the ADI Oversight Team of the TRB presentation for review and approval.
class TebAdiNotification extends Mailable
{
    public function __construct(public TebProject $project, public ?string $presentationAt, public ?string $location, public ?string $notes) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'TRB for ADI review: ' . ($this->project->teb_no ? "{$this->project->teb_no} " : '') . $this->project->title);
    }

    public function content(): Content
    {
        return new Content(view: 'teb.mail.adi-notification', with: ['p' => $this->project]);
    }
}
