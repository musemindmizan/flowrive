<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Queued so a slow mail server never holds up the request that sent it.
 */
class WorkspaceInvitation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "{$this->invitation->inviter->name} invited you to {$this->invitation->workspace->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.workspace-invitation',
            with: [
                'workspace' => $this->invitation->workspace->name,
                'inviter' => $this->invitation->inviter->name,
                'role' => $this->invitation->role->label(),
                'note' => $this->invitation->message,
                'expires' => $this->invitation->expires_at->toFormattedDateString(),
                // Accepting is not built yet, so the link lands on sign-in
                'url' => route('login'),
            ],
        );
    }
}
