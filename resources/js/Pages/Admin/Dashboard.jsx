import AdminLayout from '@/Layouts/AdminLayout'
import { Link } from '@inertiajs/react'

function StatCard({ label, value, sub }) {
    return (
        <div className="bg-surface rounded-xl border border-surface-border p-5">
            <p className="text-xs font-medium text-app-text-muted uppercase tracking-wide mb-1">{label}</p>
            <p className="text-3xl font-bold text-app-text-primary">{value}</p>
            {sub && <p className="text-xs text-app-text-muted mt-1">{sub}</p>}
        </div>
    )
}

function ActionCard({ href, icon, title, description }) {
    return (
        <Link
            href={href}
            className="group bg-surface rounded-xl border border-surface-border p-6 hover:border-brand hover:shadow-md transition-all duration-200 flex items-start gap-4"
        >
            <div className="w-10 h-10 rounded-lg bg-brand-light flex items-center justify-center flex-shrink-0 text-brand group-hover:bg-brand group-hover:text-white transition-all duration-200">
                {icon}
            </div>
            <div>
                <h3 className="font-semibold text-app-text-primary group-hover:text-brand transition-colors">{title}</h3>
                <p className="text-sm text-app-text-muted mt-0.5">{description}</p>
            </div>
        </Link>
    )
}

export default function Dashboard({ tenant, stats }) {
    return (
        <AdminLayout title="Dashboard">
            {/* Conjunto info */}
            {tenant && (
                <div className="bg-surface rounded-xl border border-surface-border p-5 mb-6 flex items-center justify-between">
                    <div>
                        <p className="text-xs text-app-text-muted uppercase tracking-wide font-medium mb-0.5">Conjunto activo</p>
                        <h2 className="text-lg font-bold text-app-text-primary">{tenant.nombre}</h2>
                        <p className="text-sm text-app-text-muted">
                            {tenant.nit && <span>NIT {tenant.nit}</span>}
                            {tenant.ciudad && <span> · {tenant.ciudad}</span>}
                        </p>
                    </div>
                    <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${tenant.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                        {tenant.activo ? 'Activo' : 'Inactivo'}
                    </span>
                </div>
            )}

            {/* Stats */}
            <div className="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-8">
                <StatCard label="Copropietarios" value={stats?.copropietarios ?? 0} />
                <StatCard label="Reuniones" value={stats?.reuniones_total ?? 0} />
                <StatCard
                    label="En curso"
                    value={stats?.reuniones_activas ?? 0}
                    sub="reuniones activas ahora"
                />
            </div>

            {/* Acciones rápidas */}
            <h2 className="text-sm font-semibold text-app-text-muted uppercase tracking-wide mb-3">Acciones rápidas</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <ActionCard
                    href="/admin/copropietarios"
                    title="Copropietarios"
                    description="Ver, crear y editar copropietarios del conjunto"
                    icon={
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    }
                />
                <ActionCard
                    href="/admin/reuniones"
                    title="Reuniones"
                    description="Crear y gestionar asambleas del conjunto"
                    icon={
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <rect x="3" y="4" width="18" height="18" rx="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    }
                />
                <ActionCard
                    href="/admin/padron"
                    title="Importar CSV"
                    description="Importar copropietarios masivamente desde archivo CSV"
                    icon={
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                    }
                />
            </div>
        </AdminLayout>
    )
}
