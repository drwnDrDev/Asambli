<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;

class MagicLinkController extends Controller
{
    public function __construct(private MagicLinkService $service) {}

    public function acceder(string $token)
    {
        $link = $this->service->find($token);

        if ($link && $link->reunion_id) {
            return redirect("/sala/login/{$link->reunion_id}");
        }

        return redirect('/login')->withErrors(['token' => 'Link inválido o expirado.']);
    }
}
