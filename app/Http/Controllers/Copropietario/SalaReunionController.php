<?php

namespace App\Http\Controllers\Copropietario;

use App\Http\Controllers\Controller;
use App\Models\Reunion;
use Inertia\Inertia;

class SalaReunionController extends Controller
{
    public function index()
    {
        return Inertia::render('Copropietario/Sala/Index');
    }

    public function show(Reunion $reunion)
    {
        return Inertia::render('Copropietario/Sala/Show', compact('reunion'));
    }

    public function historial()
    {
        return Inertia::render('Copropietario/Sala/Historial');
    }
}
