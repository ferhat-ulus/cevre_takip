<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $tasks;
    public $stats;

    public function __construct($user, $tasks, $stats)
    {
        $this->user = $user;
        $this->tasks = $tasks;
        $this->stats = $stats;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Çevre Takip Özeti: ' . $this->stats['overdue'] . ' Gecikmiş, ' . $this->stats['crit'] . ' Kritik İş',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.digest',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}