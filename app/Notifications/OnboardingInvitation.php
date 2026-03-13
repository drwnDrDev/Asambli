<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OnboardingInvitation extends Notification
{
    use Queueable;

    public function __construct(private string $url) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Bienvenido a ASAMBLI — Configura tu acceso')
            ->greeting("Hola, {$notifiable->name}")
            ->line('El administrador de tu conjunto te ha registrado en ASAMBLI. Por favor configura tu contraseña y confirma tus datos.')
            ->action('Configurar mi acceso', $this->url)
            ->line('Este enlace es válido por 48 horas.');
    }
}
