<?php

namespace App\Notifications;

use App\Models\Reunion;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccesoDelegadoNotification extends Notification
{
    public function __construct(
        public readonly Reunion $reunion,
        public readonly string $pin
    ) {}

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $loginUrl = url("/sala/login/{$this->reunion->id}");
        $fecha = $this->reunion->fecha_programada?->format('d/m/Y H:i') ?? 'Por confirmar';
        $nombre = $notifiable->user?->name ?? $notifiable->email ?? 'Delegado';

        return (new MailMessage)
            ->subject("Acceso como delegado: {$this->reunion->titulo}")
            ->greeting("Hola, {$nombre}")
            ->line("Has sido designado como delegado (apoderado) en la siguiente reunión:")
            ->line("**{$this->reunion->titulo}**")
            ->line("Fecha: {$fecha}")
            ->line("---")
            ->line("**Tu PIN de acceso es: {$this->pin}**")
            ->line("Guarda este PIN — lo necesitarás junto con tu número de documento para ingresar como delegado.")
            ->action('Ingresar a la reunión', $loginUrl)
            ->line("También puedes ingresar directamente con tu documento y PIN en el sitio de la reunión.");
    }
}
