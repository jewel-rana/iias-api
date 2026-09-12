<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $code) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your password reset code',
        );
    }

    public function content(): Content
    {
        $code = e($this->code);

        return new Content(
            htmlString: <<<HTML
<p>Your password reset code is <strong>{$code}</strong>.</p>
<p>This code expires in 15 minutes. If you did not request it, you can ignore this email.</p>
HTML,
        );
    }
}
