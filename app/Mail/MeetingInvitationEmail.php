<?php

namespace App\Mail;

use App\Models\Crm\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Spatie\IcalendarGenerator\Components\Calendar;
use Spatie\IcalendarGenerator\Components\Event;

class MeetingInvitationEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public Activity $activity;

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Invitación a Reunión: ' . $this->activity->meeting_title,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.meetings.invitation',
        );
    }

    /**
     * ✅ Adjunta el archivo .ics del evento al correo.
     */
    public function attachments(): array
    {
        // La lógica para crear el evento es la misma
        $event = Event::create()
            ->name($this->activity->meeting_title)
            ->description($this->activity->description)
            ->startsAt($this->activity->schedule_date)
            ->endsAt($this->activity->schedule_date->addHour());

        // ✅ La única diferencia es que usamos ->toString() para obtener el contenido del calendario
        $calendarContent = Calendar::create()
            ->event($event)
            ->toString();

        // Adjuntamos el contenido como un archivo .ics
        return [
            \Illuminate\Mail\Mailables\Attachment::fromData(fn () => $calendarContent, 'invitation.ics')
                ->withMime('text/calendar; charset=UTF-8; method=REQUEST'),
        ];
    }
}