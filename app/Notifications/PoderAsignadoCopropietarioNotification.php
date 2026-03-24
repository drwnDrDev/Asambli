<?php

namespace App\Notifications;

use App\Models\Poder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PoderAsignadoCopropietarioNotification extends Notification
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

        return (new MailMessage)
            ->subject('Has sido designado delegado — ASAMBLI')
            ->greeting("Hola, {$notifiable->name}")
            ->line("{$poderdante} te ha designado como su representante (delegado) para la próxima reunión de copropietarios.")
            ->line('Estás autorizado para votar en su nombre. Cuando la reunión esté disponible, accede a tu sala con el siguiente enlace.')
            ->action('Ir a mi sala', $this->url)
            ->line('Este enlace es válido por 48 horas y es de uso personal e intransferible.');
    }
}
