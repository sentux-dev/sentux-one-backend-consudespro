<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class TaskDigestEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public Collection $overdueTasks;
    public Collection $dueTodayTasks;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Collection $overdueTasks, Collection $dueTodayTasks)
    {
        $this->user = $user;
        $this->overdueTasks = $overdueTasks;
        $this->dueTodayTasks = $dueTodayTasks;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tu Resumen de Tareas para Hoy',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.tasks.digest',
        );
    }
}