<?php

namespace App\Mail;

use App\Models\OwnerInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $invitation;
    public $inviteLink;

    public function __construct(OwnerInvitation $invitation, string $inviteLink)
    {
        $this->invitation = $invitation;
        $this->inviteLink = $inviteLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitation à rejoindre myTerrain en tant que Loueur',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.owner_invitation',
        );
    }
}
