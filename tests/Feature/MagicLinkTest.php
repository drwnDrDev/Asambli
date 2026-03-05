<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\MagicLinkService;

test('magic link is created for user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $service = app(MagicLinkService::class);

    $link = $service->generate($user);

    expect($link)->toContain('/acceso/');
    expect($user->magicLinks()->count())->toBe(1);
});

test('magic link expires after 48 hours', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'copropietario']);
    $service = app(MagicLinkService::class);
    $service->generate($user);

    $magicLink = $user->magicLinks()->first();
    $magicLink->update(['expires_at' => now()->subHour()]);

    $response = $this->get('/acceso/' . $magicLink->token);
    $response->assertStatus(410); // Gone
});
