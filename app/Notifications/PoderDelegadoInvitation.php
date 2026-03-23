<?php

namespace App\Notifications;

use App\Models\Poder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PoderDelegadoInvitation extends Notification
{
    use Queueable;

    public function __construct(
        private string $url,
        private Poder $poder
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $poderdante = $this->poder->poderdante?->user?->name ?? 'un copropietario';
        $reunion = $this->poder->reunion?->titulo ?? 'la reunión';

        return (new MailMessage)
            ->subject("Ha sido invitado como Delegado — {$reunion}")
            ->greeting("Hola, {$notifiable->name}")
            ->line("{$poderdante} lo ha designado como su representante (delegado) en la reunión: \"{$reunion}\".")
            ->line('Usted está autorizado para votar en nombre de dicho copropietario.')
            ->action('Acceder como Delegado', $this->url)
            ->line('Este enlace es válido por 48 horas y es de uso personal e intransferible.');
    }
}
