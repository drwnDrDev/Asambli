import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage, router } from '@inertiajs/react'

export default function Index({ copropietarios = [] }) {
    const { flash } = usePage().props

    const destroy = (id) => {
        if (confirm('¿Eliminar este copropietario? Esta acción también eliminará su usuario.')) {
            router.delete(`/admin/copropietarios/${id}`, { preserveScroll: true })
        }
    }

    return (
        <AdminLayout title="Copropietarios">
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <div className="flex justify-end mb-4">
                <Link
                    href="/admin/copropietarios/create"
                    className="inline-flex items-center gap-2 px-4 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                    </svg>
                    Nuevo copropietario
                </Link>
            </div>

            <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                {copropietarios.length === 0 ? (
                    <div className="text-center py-16 text-app-text-muted">
                        <svg className="w-10 h-10 mx-auto mb-3 opacity-30" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                        <p className="text-sm">No hay copropietarios registrados.</p>
                        <Link href="/admin/copropietarios/create" className="text-sm text-brand hover:underline mt-1 inline-block">
                            Crear el primero
                        </Link>
                    </div>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-content-bg border-b border-surface-border">
                            <tr>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Nombre</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Email</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Unidad</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Coeficiente</th>
                                <th className="text-left px-5 py-3 font-medium text-app-text-muted">Estado</th>
                                <th className="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-surface-border">
                            {copropietarios.map(c => (
                                <tr key={c.id} className="hover:bg-surface-hover transition-colors">
                                    <td className="px-5 py-3.5 font-medium text-app-text-primary">{c.user?.name}</td>
                                    <td className="px-5 py-3.5 text-app-text-secondary">{c.user?.email}</td>
                                    <td className="px-5 py-3.5 text-app-text-secondary">
                                        {c.unidad ? `Unidad ${c.unidad.numero}` : '—'}
                                    </td>
                                    <td className="px-5 py-3.5 font-mono text-app-text-secondary text-xs">
                                        {c.unidad?.coeficiente ?? '—'}%
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                            {c.activo ? 'Activo' : 'Inactivo'}
                                        </span>
                                    </td>
                                    <td className="px-5 py-3.5">
                                        <div className="flex items-center justify-end gap-3">
                                            <Link href={`/admin/copropietarios/${c.id}`} className="text-xs text-brand hover:underline">
                                                Ver
                                            </Link>
                                            <Link href={`/admin/copropietarios/${c.id}/edit`} className="text-xs text-app-text-secondary hover:text-brand">
                                                Editar
                                            </Link>
                                            <button
                                                onClick={() => destroy(c.id)}
                                                className="text-xs text-app-text-muted hover:text-danger transition-colors"
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </AdminLayout>
    )
}
