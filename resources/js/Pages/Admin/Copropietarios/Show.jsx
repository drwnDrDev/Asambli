import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage, router } from '@inertiajs/react'

export default function Show({ copropietario }) {
    const { flash } = usePage().props
    const { user, unidades = [] } = copropietario

    const coefTotal = unidades.reduce((s, u) => s + parseFloat(u.coeficiente ?? 0), 0)

    const destroy = () => {
        if (confirm('¿Eliminar este copropietario y su usuario asociado?')) {
            router.delete(`/admin/copropietarios/${copropietario.id}`)
        }
    }

    return (
        <AdminLayout title={user?.name ?? 'Copropietario'}>
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <div className="mb-5 flex items-center gap-3">
                <Link href="/admin/copropietarios" className="text-sm text-app-text-muted hover:text-brand transition-colors">
                    ← Copropietarios
                </Link>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-5">
                {/* Info principal */}
                <div className="lg:col-span-2 bg-surface rounded-xl border border-surface-border p-6">
                    <div className="flex items-start justify-between mb-5">
                        <div className="flex items-center gap-3">
                            <div className="w-12 h-12 rounded-full bg-brand-light flex items-center justify-center text-brand font-bold text-lg flex-shrink-0">
                                {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                            </div>
                            <div>
                                <h2 className="text-lg font-bold text-app-text-primary">{user?.name}</h2>
                                <p className="text-sm text-app-text-muted">{user?.email}</p>
                            </div>
                        </div>
                        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${copropietario.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                            {copropietario.activo ? 'Activo' : 'Inactivo'}
                        </span>
                    </div>

                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Documento</dt>
                            <dd className="text-app-text-primary">
                                {copropietario.tipo_documento && copropietario.numero_documento
                                    ? `${copropietario.tipo_documento} ${copropietario.numero_documento}`
                                    : '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Teléfono</dt>
                            <dd className="text-app-text-primary">{copropietario.telefono ?? '—'}</dd>
                        </div>
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Es residente</dt>
                            <dd className="text-app-text-primary">{copropietario.es_residente ? 'Sí' : 'No'}</dd>
                        </div>
                        <div>
                            <dt className="text-app-text-muted font-medium mb-0.5">Coef. total</dt>
                            <dd className="font-mono text-app-text-primary">{coefTotal > 0 ? `${coefTotal.toFixed(5)}%` : '—'}</dd>
                        </div>
                    </dl>
                </div>

                {/* Unidades */}
                <div className="bg-surface rounded-xl border border-surface-border p-6">
                    <h3 className="text-sm font-semibold text-app-text-muted uppercase tracking-wide mb-4">
                        Unidades ({unidades.length})
                    </h3>
                    {unidades.length > 0 ? (
                        <div className="space-y-3">
                            {unidades.map(u => (
                                <dl key={u.id} className="text-sm border-b border-surface-border pb-3 last:border-0 last:pb-0">
                                    <div className="flex justify-between items-center">
                                        <dd className="text-app-text-primary font-semibold">Unidad {u.numero}</dd>
                                        <dd className="font-mono text-xs text-app-text-secondary">{u.coeficiente}%</dd>
                                    </div>
                                    <dd className="text-app-text-muted capitalize text-xs mt-0.5">
                                        {u.tipo}{u.torre ? ` · Torre ${u.torre}` : ''}{u.piso ? ` · Piso ${u.piso}` : ''}
                                    </dd>
                                </dl>
                            ))}
                        </div>
                    ) : (
                        <p className="text-sm text-app-text-muted">Sin unidades asignadas</p>
                    )}
                </div>
            </div>

            {/* Acceso: Onboarding y PIN */}
            <div className="mt-5 grid grid-cols-1 lg:grid-cols-2 gap-5">
                {/* Estado de activación */}
                <div className="bg-surface rounded-xl border border-surface-border p-6">
                    <h3 className="text-sm font-semibold text-app-text-muted uppercase tracking-wide mb-4">
                        Estado de activación
                    </h3>
                    {user?.onboarded_at ? (
                        <div className="text-success text-[13px] mb-4">
                            ✓ Activo desde {new Date(user.onboarded_at).toLocaleDateString('es-CO')}
                        </div>
                    ) : (
                        <div className="mb-4">
                            <span className="text-[12px] text-sidebar-text bg-warning/20 px-2 py-0.5 rounded">Sin activar</span>
                        </div>
                    )}
                    <button
                        onClick={() => router.post(route('admin.copropietarios.reenviar-bienvenida', copropietario.id))}
                        className="px-3 py-1.5 border border-surface-border text-xs font-medium text-app-text-secondary hover:text-brand hover:border-brand rounded-lg transition-colors"
                    >
                        Reenviar bienvenida
                    </button>
                </div>

                {/* PIN de acceso rápido */}
                <div className="bg-surface rounded-xl border border-surface-border p-6">
                    <h3 className="text-sm font-semibold text-app-text-muted uppercase tracking-wide mb-4">
                        PIN de acceso rápido
                    </h3>
                    {user?.quick_pin ? (
                        <div className="mb-3 text-sm">
                            <p className="text-app-text-muted mb-0.5">PIN actual: <code className="font-mono text-app-text-primary bg-content-bg px-1.5 py-0.5 rounded">{user.quick_pin}</code></p>
                            <p className="text-xs text-app-text-muted">Vence: {new Date(user.pin_expires_at).toLocaleString('es-CO')}</p>
                        </div>
                    ) : (
                        <p className="text-sidebar-text text-[13px] mb-3">Sin PIN generado</p>
                    )}
                    {flash?.pin && (
                        <div className="bg-success-bg border border-success rounded p-3 mb-3">
                            <p className="text-success font-mono text-lg font-bold">{flash.pin}</p>
                            <p className="text-[12px] text-success">Copia este PIN y entrégalo al copropietario</p>
                        </div>
                    )}
                    <button
                        onClick={() => router.post(route('admin.copropietarios.generar-pin', copropietario.id))}
                        className="px-3 py-1.5 border border-surface-border text-xs font-medium text-app-text-secondary hover:text-brand hover:border-brand rounded-lg transition-colors"
                    >
                        Generar nuevo PIN
                    </button>
                </div>
            </div>

            <div className="mt-5 flex items-center gap-3">
                <Link
                    href={`/admin/copropietarios/${copropietario.id}/edit`}
                    className="px-4 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    Editar
                </Link>
                <button
                    onClick={destroy}
                    className="px-4 py-2 border border-surface-border text-sm font-medium text-danger hover:bg-danger-bg rounded-lg transition-colors"
                >
                    Eliminar
                </button>
            </div>
        </AdminLayout>
    )
}
