<?php

namespace App\Services;

use App\Models\Copropietario;
use App\Models\Reunion;
use App\Models\User;
use App\Notifications\ConvocatoriaReunion;

class ConvocatoriaService
{
    public function __construct(private MagicLinkService $magicLinkService) {}

    public function enviar(Reunion $reunion, User $admin): void
    {
        $copropietarios = Copropietario::withoutGlobalScopes()
            ->where('tenant_id', $reunion->tenant_id)
            ->where('activo', true)
            ->with('user')
            ->get();

        foreach ($copropietarios as $copropietario) {
            $link = $this->magicLinkService->generate($copropietario->user, $reunion->id);
            $copropietario->user->notify(new ConvocatoriaReunion($reunion, $link));
        }

        $reunion->update(['convocatoria_enviada_at' => now()]);
        $reunion->transicionarA('convocada', $admin, ['total_notificados' => $copropietarios->count()]);
    }
}
