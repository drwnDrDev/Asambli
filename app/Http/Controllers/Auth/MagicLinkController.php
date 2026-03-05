<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\MagicLinkService;

class MagicLinkController extends Controller
{
    public function __construct(private MagicLinkService $service) {}

    public function acceder(string $token)
    {
        $link = $this->service->validate($token);

        if (!$link) {
            abort(410, 'Este enlace ha expirado o ya fue utilizado.');
        }

        $this->service->consume($link);
        auth()->login($link->user);

        return redirect()->route('sala.index');
    }
}
