<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConvocatoriaReunion extends Notification
{
    use Queueable;

    public function __construct(
        public readonly \App\Models\Reunion $reunion,
        public readonly string $magicLink
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Convocatoria: {$this->reunion->titulo}")
            ->greeting("Estimado/a {$notifiable->name},")
            ->line("Está convocado/a a la siguiente reunión:")
            ->line("**{$this->reunion->titulo}**")
            ->line("Fecha: " . $this->reunion->fecha_programada?->format('d/m/Y H:i'))
            ->action('Acceder a la Reunión', $this->magicLink)
            ->line("Este enlace es personal e intransferible. Válido por 48 horas.")
            ->salutation("Atentamente, la Administración");
    }
}
