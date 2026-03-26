import AdminLayout from '@/Layouts/AdminLayout'
import { Link } from '@inertiajs/react'

export default function Dashboard({ stats, tenants_recientes }) {
    const statCards = [
        { label: 'Conjuntos activos', value: stats.tenants_activos, color: 'text-success' },
        { label: 'Conjuntos inactivos', value: stats.tenants_inactivos, color: 'text-app-text-muted' },
        { label: 'Reuniones activas', value: stats.reuniones_activas, color: 'text-brand' },
        { label: 'Usuarios totales', value: stats.total_usuarios, color: 'text-app-text-primary' },
    ]

    return (
        <AdminLayout title="Dashboard">
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                {statCards.map(card => (
                    <div key={card.label} className="bg-surface rounded-xl border border-surface-border p-5">
                        <p className="text-xs text-app-text-muted mb-1">{card.label}</p>
                        <p className={`text-3xl font-bold ${card.color}`}>{card.value}</p>
                    </div>
                ))}
            </div>

            <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                <div className="px-5 py-4 border-b border-surface-border flex justify-between items-center">
                    <h2 className="text-sm font-semibold text-app-text-primary">Conjuntos recientes</h2>
                    <Link href="/super-admin/tenants" className="text-xs text-brand hover:underline">Ver todos →</Link>
                </div>
                <table className="w-full text-sm">
                    <thead className="bg-content-bg border-b border-surface-border">
                        <tr>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Nombre</th>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Ciudad</th>
                            <th className="text-left px-5 py-3 font-medium text-app-text-muted">Estado</th>
                            <th className="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-surface-border">
                        {tenants_recientes.map(t => (
                            <tr key={t.id} className="hover:bg-surface-hover transition-colors">
                                <td className="px-5 py-3 font-medium text-app-text-primary">{t.nombre}</td>
                                <td className="px-5 py-3 text-app-text-secondary">{t.ciudad ?? '—'}</td>
                                <td className="px-5 py-3">
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${t.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                        {t.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-5 py-3 text-right">
                                    <Link href={`/super-admin/tenants/${t.id}`} className="text-xs text-brand hover:underline">Ver</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    )
}
