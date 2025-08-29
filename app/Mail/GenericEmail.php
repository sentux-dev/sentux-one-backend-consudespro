<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Log;

class GenericEmail extends Mailable
{
    use Queueable, SerializesModels;

    /** @var array{subject:string, body:string, from_email?:string, from_name?:string, reply_to?:string, text_body?:string} */
    public $emailData;

    /** @var array<int, array{name:string, path:string}> */
    public $attachments;

    public function __construct(array $emailData, array $attachments = [])
    {
        $this->emailData   = $emailData;
        $this->attachments = $attachments;
    }

    public function envelope(): Envelope
    {
        $fromEmail = $this->emailData['from_email'] ?? null;
        $fromName  = $this->emailData['from_name']  ?? null;

        $replyTo = $this->emailData['reply_to'] ?? null;

        $envelope = new Envelope(
            subject: $this->emailData['subject'],
        );

        if ($fromEmail) {
            $envelope->from($fromName ? new \Illuminate\Mail\Mailables\Address($fromEmail, $fromName) : $fromEmail);
        }

        if ($replyTo) {
            $envelope->replyTo($replyTo);
        }

        return $envelope;
    }

    public function content(): Content
    {
        $content = new Content(
            htmlString: $this->emailData['body'],
        );

        if (!empty($this->emailData['text_body'])) {
            $content->text($this->emailData['text_body']);
        }

        return $content;
    }

    public function attachments(): array
    {
        $emailAttachments = [];

        foreach ($this->attachments as $attachment) {
            try {
                $emailAttachments[] = Attachment::fromPath($attachment['path'])
                    ->as($attachment['name']);
            } catch (\Exception $e) {
                Log::error('Error attaching file: ' . $e->getMessage());
            }
        }

        return $emailAttachments;
    }
}
