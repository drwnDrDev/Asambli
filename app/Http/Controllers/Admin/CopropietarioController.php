<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccesoReunion;
use App\Models\Copropietario;
use App\Models\Poder;
use App\Models\Unidad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CopropietarioController extends Controller
{
    public function index(Request $request)
    {
        $tab    = in_array($request->get('tab'), ['copropietarios', 'externos'])
            ? $request->get('tab')
            : 'copropietarios';
        $search = $request->get('search', '');

        $esExterno = $tab === 'externos';

        $query = Copropietario::with(['unidades'])
            ->where('es_externo', $esExterno)
            ->withCount([
                'poderesOtorgados as poderes_activos_count' => fn ($q) =>
                    $q->whereIn('estado', ['pendiente', 'aprobado']),
            ]);

        if ($esExterno) {
            $query->with(['ultimoPoderComoApoderado' => function ($q) {
                $q->with('reunion:id,titulo,estado');
            }]);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('numero_documento', 'like', "%{$search}%");
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
        $tenant = app('current_tenant');

        $data = $request->validate([
            'nombre'          => 'required|string|max:255',
            'email'           => ['required', 'email', Rule::unique('copropietarios', 'email')->where('tenant_id', $tenant->id)],
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        DB::transaction(function () use ($data, $tenant) {
            $copropietario = Copropietario::create([
                'tenant_id'        => $tenant->id,
                'nombre'           => $data['nombre'],
                'email'            => $data['email'],
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

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Copropietario creado exitosamente.');
    }

    public function show(Copropietario $copropietario)
    {
        $copropietario->load(['unidades']);

        $poderesOtorgados = Poder::withoutGlobalScopes()
            ->where('poderdante_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->with('apoderado', 'reunion:id,titulo,estado')
            ->orderByDesc('created_at')
            ->get();

        $poderesRecibidos = Poder::withoutGlobalScopes()
            ->where('apoderado_id', $copropietario->id)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->with('poderdante.unidades', 'reunion:id,titulo,estado')
            ->orderByDesc('created_at')
            ->get();

        $accesos = AccesoReunion::withoutGlobalScopes()
            ->where('copropietario_id', $copropietario->id)
            ->where('activo', true)
            ->whereNotNull('pin_plain')
            ->with('reunion:id,titulo,fecha_programada,estado')
            ->orderByDesc('updated_at')
            ->get()
            ->map(fn ($a) => [
                'reunion_id'    => $a->reunion_id,
                'titulo'        => $a->reunion?->titulo,
                'fecha'         => $a->reunion?->fecha_programada?->format('d/m/Y'),
                'estado'        => $a->reunion?->estado instanceof \BackedEnum ? $a->reunion->estado->value : $a->reunion?->estado,
                'pin'           => $a->pin_plain,
            ]);

        return Inertia::render('Admin/Copropietarios/Show', [
            'copropietario'    => $copropietario,
            'poderesOtorgados' => $poderesOtorgados,
            'poderesRecibidos' => $poderesRecibidos,
            'accesos'          => $accesos,
        ]);
    }

    public function edit(Copropietario $copropietario)
    {
        $copropietario->load(['unidades']);
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
            'email'           => ['required', 'email', Rule::unique('copropietarios', 'email')->where('tenant_id', $copropietario->tenant_id)->ignore($copropietario->id)],
            'tipo_documento'  => 'nullable|in:CC,CE,NIT,PP,TI,PEP',
            'numero_documento'=> 'nullable|string|max:30',
            'telefono'        => 'nullable|string|max:20',
            'es_residente'    => 'boolean',
            'activo'          => 'boolean',
            'unidades'        => 'array',
            'unidades.*'      => 'exists:unidades,id',
        ]);

        DB::transaction(function () use ($data, $copropietario) {
            $copropietario->update([
                'nombre'           => $data['nombre'],
                'email'            => $data['email'],
                'tipo_documento'   => $data['tipo_documento'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'telefono'         => $data['telefono'] ?? null,
                'es_residente'     => $data['es_residente'] ?? false,
                'activo'           => $data['activo'] ?? true,
            ]);

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
        if ($copropietario->es_externo) {
            $tienePoderActivo = Poder::where('apoderado_id', $copropietario->id)
                ->whereIn('estado', ['pendiente', 'aprobado'])
                ->whereHas('reunion', fn ($q) =>
                    $q->whereNotIn('estado', ['finalizada', 'cancelada'])
                )
                ->exists();

            if ($tienePoderActivo) {
                return back()->with('error', 'No se puede eliminar: este delegado tiene un poder activo en una reunión vigente. Revoca el poder primero.');
            }
        }

        $copropietario->delete();

        return redirect()->route('admin.copropietarios.index')
            ->with('success', 'Eliminado correctamente.');
    }
}
