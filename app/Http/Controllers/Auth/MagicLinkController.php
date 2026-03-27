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
            return auth()->check()
                ? redirect()->route('sala.index')
                : redirect()->route('login');
        }

        $this->service->consume($link);
        auth()->login($link->user);

        return redirect()->route('sala.index');
    }
}
