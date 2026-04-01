<?php

namespace App\Services;

use App\Models\MagicLink;
use App\Models\User;
use Illuminate\Support\Str;

class MagicLinkService
{
    public function generate(User $user, ?int $reunionId = null, string $type = 'convocatoria'): string
    {
        $token = Str::random(64);

        MagicLink::create([
            'user_id' => $user->id,
            'reunion_id' => $reunionId,
            'token' => $token,
            'type' => $type,
            'expires_at' => now()->addHours(48),
        ]);

        if ($type === 'onboarding') {
            return url('/bienvenida/' . $token);
        }

        return url('/acceso/' . $token);
    }

    public function validate(string $token): ?MagicLink
    {
        $link = MagicLink::with('user')
            ->where('token', $token)
            ->first();

        if (!$link || !$link->isValid()) {
            return null;
        }

        return $link;
    }

    public function find(string $token): ?MagicLink
    {
        return MagicLink::where('token', $token)->first();
    }

    public function consume(MagicLink $link): void
    {
        $link->update(['used_at' => now()]);
    }
}
