<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('presence-reunion.{reunionId}', function ($user, $reunionId) {
    $copropietario = $user->copropietario()->with('unidades')->first();
    $primeraUnidad = $copropietario?->unidades->first();

    return [
        'id'     => $user->id,
        'nombre' => $user->name,
        'unidad' => $primeraUnidad?->numero,
        'coef'   => $primeraUnidad?->coeficiente,
        'rol'    => $user->rol,
    ];
});
