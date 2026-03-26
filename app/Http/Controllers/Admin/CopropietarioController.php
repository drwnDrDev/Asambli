<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Unidad;
use App\Models\User;
use App\Notifications\OnboardingInvitation;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CopropietarioController extends Controller
{
    public function index(Request $request)
    {
        $tab    = $request->get('tab', 'copropietarios'); // 'copropietarios' | 'externos'
        $search = $request->get('search', '');

        $esExterno = $tab === 'externos';

        $query = Copropietario::with(['user', 'unidades'])
            ->where('es_externo', $esExterno)
            ->withCount([
                'poderesOtorgados as poderes_activos_count' => fn ($q) =>
                    $q->whereIn('estado', ['pendiente', 'aprobado']),
            ]);

        if ($esExterno) {
            // Load poderes for externos to show status badge
            // NOTE: Do NOT use ->limit() inside with() — it breaks eager loading
            // Use [0] in the JSX to get the most recent poder
            $query->with(['poderesComoApoderado' => function ($q) {
                $q->with('reunion:id,titulo,estado')->orderByDesc('created_at');
            }]);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', fn ($sq) =>
                    $sq->where('name', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                )->orWhere('numero_documento', 'like', "%{$search}%");
            });
        }

        $copropietarios = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Copropietarios/Index', [
            'copropietarios' => $copropietarios,
            'filters'        => ['tab' => $tab, 'search' => $search],
        ]);
    }

    public function create()
    {
        $unidades = Unidad::whereNull('copropietario_id')->orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Create', [
            'unidades' => $unidades,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        $tenant = app('current_tenant');

        $user = null;

        DB::transaction(function () use ($data, $tenant, &$user) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'name'      => $data['nombre'],
                'email'     => $data['email'],
                'password'  => bcrypt(Str::random(16)),
                'rol'       => 'copropietario',
            ]);

            $copropietario = Copropietario::create([
                'tenant_id'        => $tenant->id,
                'user_id'          => $user->id,
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => true,
            ]);

            if (!empty($data['unidades'])) {
                Unidad::whereIn('id', $data['unidades'])->update(['copropietario_id' => $copropietario->id]);
            }
        });

        // Enviar invitación de onboarding
        $onboardingUrl = app(MagicLinkService::class)->generate($user, null, 'onboarding');
        $user->notify(new OnboardingInvitation($onboardingUrl));

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario creado exitosamente.');
    }

    public function show(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidades']);

        $poderesActivos = Poder::withoutGlobalScopes()
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->with('apoderado.user', 'reunion')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Copropietarios/Show', [
            'copropietario'  => $copropietario,
            'poderesActivos' => $poderesActivos,
        ]);
    }

    public function edit(Copropietario $copropietario)
    {
        $copropietario->load(['user', 'unidades']);
        // Unidades libres + las ya asignadas a este copropietario
        $unidades = Unidad::where(function ($q) use ($copropietario) {
            $q->whereNull('copropietario_id')
              ->orWhere('copropietario_id', $copropietario->id);
        })->orderBy('numero')->get();

        return Inertia::render('Admin/Copropietarios/Edit', [
            'copropietario' => $copropietario,
            'unidades'      => $unidades,
        ]);
    }

    public function update(Request $request, Copropietario $copropietario)
    {
        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,' . $copropietario->user_id,
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'activo'          => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        DB::transaction(function () use ($data, $copropietario) {
            $copropietario->user->update([
                'name'  => $data['nombre'],
                'email' => $data['email'],
            ]);

            $copropietario->update([
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => $data['activo'] ?? true,
            ]);

            // Desasignar todas sus unidades, reasignar las enviadas
            Unidad::where('copropietario_id', $copropietario->id)->update(['copropietario_id' => null]);
            if (!empty($data['unidades'])) {
                Unidad::whereIn('id', $data['unidades'])->update(['copropietario_id' => $copropietario->id]);
            }
        });

        return redirect()->route('admin.copropietarios.show', $copropietario)
            ->with('success', 'Copropietario actualizado.');
    }

    public function destroy(Copropietario $copropietario)
    {
        $user = $copropietario->user;
        $copropietario->delete(); // nullOnDelete liberará sus unidades
        $user?->delete();

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario eliminado.');
    }

    public function generatePin(Copropietario $copropietario)
    {
        $pin = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $copropietario->user->update([
            'quick_pin' => $pin,
            'pin_expires_at' => now()->addHours(72),
        ]);
        return back()->with('pin', $pin)->with('success', 'PIN generado correctamente.');
    }

    public function reenviarBienvenida(Copropietario $copropietario)
    {
        $copropietario->load('user');
        $onboardingUrl = app(MagicLinkService::class)->generate($copropietario->user, null, 'onboarding');
        $copropietario->user->notify(new OnboardingInvitation($onboardingUrl));
        return back()->with('success', 'Invitación de bienvenida reenviada.');
    }
}
