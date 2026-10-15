<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerTemporaryCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $temporaryPassword;
    public $businessName;
    public $loginLink;

    public function __construct(User $user, string $temporaryPassword, string $businessName, string $loginLink)
    {
        $this->user = $user;
        $this->temporaryPassword = $temporaryPassword;
        $this->businessName = $businessName;
        $this->loginLink = $loginLink;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vos accès Loueur Partenaire myTerrain (Mot de passe temporaire)',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.owner_temporary_credentials',
        );
    }
}
