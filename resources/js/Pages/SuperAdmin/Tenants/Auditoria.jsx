import AdminLayout from '@/Layouts/AdminLayout'
import { Link, router } from '@inertiajs/react'
import { useState } from 'react'

export default function Auditoria({ tenant, logs, reuniones, filters }) {
    const [reunionId, setReunionId] = useState(filters.reunion_id ?? '')
    const [accion, setAccion] = useState(filters.accion ?? '')

    const applyFilter = () => {
        router.get(`/super-admin/tenants/${tenant.id}/auditoria`, {
            reunion_id: reunionId || undefined,
            accion: accion || undefined,
        }, { preserveState: true, replace: true })
    }

    const clearFilter = () => {
        setReunionId(''); setAccion('')
        router.get(`/super-admin/tenants/${tenant.id}/auditoria`, {}, { replace: true })
    }

    return (
        <AdminLayout title={`Auditoría — ${tenant.nombre}`}>
            <div className="mb-4 flex gap-3 items-center">
                <Link href={`/super-admin/tenants/${tenant.id}`} className="text-sm text-app-text-muted hover:text-brand">
                    ← Volver al conjunto
                </Link>
            </div>

            {/* Filtros */}
            <div className="bg-surface rounded-xl border border-surface-border p-4 mb-5 flex flex-wrap gap-3 items-end">
                <div>
                    <label className="block text-xs text-app-text-muted mb-1">Reunión</label>
                    <select
                        value={reunionId}
                        onChange={e => setReunionId(e.target.value)}
                        className="text-sm border border-surface-border rounded-lg px-3 py-2 bg-content-bg text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30"
                    >
                        <option value="">Todas</option>
                        {reuniones.map(r => (
                            <option key={r.id} value={r.id}>{r.titulo}</option>
                        ))}
                    </select>
                </div>
                <div>
                    <label className="block text-xs text-app-text-muted mb-1">Acción</label>
                    <input
                        type="text" placeholder="Filtrar por acción..."
                        value={accion}
                        onChange={e => setAccion(e.target.value)}
                        onKeyDown={e => e.key === 'Enter' && applyFilter()}
                        className="text-sm border border-surface-border rounded-lg px-3 py-2 bg-content-bg text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30"
                    />
                </div>
                <button onClick={applyFilter}
                    className="px-4 py-2 text-sm bg-brand hover:bg-brand-dark text-white rounded-lg transition-colors">
                    Filtrar
                </button>
                {(filters.reunion_id || filters.accion) && (
                    <button onClick={clearFilter} className="px-4 py-2 text-sm border border-surface-border rounded-lg text-app-text-secondary hover:bg-surface-hover transition-colors">
                        Limpiar
                    </button>
                )}
            </div>

            <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-content-bg border-b border-surface-border">
                        <tr>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Fecha/Hora</th>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Reunión</th>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Acción</th>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Usuario</th>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Detalle</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-surface-border">
                        {logs.data.map((log) => (
                            <tr key={log.id} className="hover:bg-surface-hover transition-colors">
                                <td className="px-5 py-3 text-app-text-muted whitespace-nowrap text-xs">{log.created_at}</td>
                                <td className="px-5 py-3 text-app-text-secondary text-xs">{log.reunion?.titulo ?? '—'}</td>
                                <td className="px-5 py-3 font-medium text-app-text-primary">{log.accion}</td>
                                <td className="px-5 py-3 text-app-text-secondary">{log.user?.name ?? '—'}</td>
                                <td className="px-5 py-3 text-app-text-muted text-xs font-mono">
                                    {log.metadata ? JSON.stringify(log.metadata) : ''}
                                    {log.observacion && <span className="ml-1 text-app-text-secondary">{log.observacion}</span>}
                                </td>
                            </tr>
                        ))}
                        {logs.data.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-5 py-10 text-center text-app-text-muted text-sm">
                                    No hay eventos registrados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                {/* Paginación */}
                {logs.last_page > 1 && (
                    <div className="px-5 py-4 border-t border-surface-border flex justify-between items-center text-sm">
                        <span className="text-app-text-muted text-xs">
                            Mostrando {logs.from}–{logs.to} de {logs.total}
                        </span>
                        <div className="flex gap-2">
                            {logs.links.map((link, i) => (
                                <button
                                    key={i}
                                    disabled={!link.url}
                                    onClick={() => link.url && router.get(link.url)}
                                    className={`px-3 py-1 text-xs rounded border transition-colors ${
                                        link.active
                                            ? 'bg-brand text-white border-brand'
                                            : 'border-surface-border text-app-text-secondary hover:bg-surface-hover disabled:opacity-40'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AdminLayout>
    )
}
