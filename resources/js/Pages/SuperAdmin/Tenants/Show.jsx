import AdminLayout from '@/Layouts/AdminLayout'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'

export default function Show({ tenant, stats, reuniones }) {
    const { flash } = usePage().props
    const [showAddAdmin, setShowAddAdmin] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        nombre: '', email: '', password: '',
    })

    const submitAdmin = (e) => {
        e.preventDefault()
        post(`/super-admin/tenants/${tenant.id}/admins`, {
            onSuccess: () => { reset(); setShowAddAdmin(false) },
        })
    }

    const toggle = (userId) => {
        router.patch(`/super-admin/tenants/${tenant.id}/users/${userId}/toggle`, {}, { preserveScroll: true })
    }

    return (
        <AdminLayout title={tenant.nombre}>
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex flex-wrap gap-3">
                <Link href="/super-admin/tenants" className="text-sm text-app-text-muted hover:text-brand">← Lista</Link>
                <Link href={`/super-admin/tenants/${tenant.id}/edit`}
                    className="text-sm px-3 py-1.5 rounded-lg border border-surface-border bg-surface hover:bg-surface-hover transition text-app-text-secondary">
                    Editar
                </Link>
                <Link href={`/super-admin/tenants/${tenant.id}/auditoria`}
                    className="text-sm px-3 py-1.5 rounded-lg border border-surface-border bg-surface hover:bg-surface-hover transition text-app-text-secondary">
                    Auditoría
                </Link>
                <Link href={`/super-admin/tenants/${tenant.id}/padron`}
                    className="text-sm px-3 py-1.5 rounded-lg border border-surface-border bg-surface hover:bg-surface-hover transition text-app-text-secondary">
                    Importar padrón
                </Link>
                <button
                    onClick={() => { if (confirm(`¿Desactivar "${tenant.nombre}"?`)) router.delete(`/super-admin/tenants/${tenant.id}`) }}
                    className="ml-auto text-sm px-3 py-1.5 rounded-lg border border-danger/30 bg-danger-bg text-danger hover:bg-danger/20 transition"
                >
                    Desactivar
                </button>
            </div>

            <div className="grid lg:grid-cols-3 gap-6">
                {/* Info del tenant */}
                <div className="bg-surface rounded-xl border border-surface-border p-6">
                    <h2 className="text-sm font-semibold text-app-text-primary mb-4">Información</h2>
                    <dl className="space-y-3 text-sm">
                        {[
                            ['Nombre', tenant.nombre],
                            ['NIT', tenant.nit],
                            ['Dirección', tenant.direccion ?? '—'],
                            ['Ciudad', tenant.ciudad ?? '—'],
                            ['Máx. poderes', tenant.max_poderes_por_delegado],
                            ['Reuniones', stats.reuniones],
                            ['Copropietarios', stats.copropietarios],
                        ].map(([label, value]) => (
                            <div key={label} className="flex justify-between gap-4 border-b border-surface-border pb-2">
                                <dt className="text-app-text-muted">{label}</dt>
                                <dd className="font-medium text-app-text-primary">{value}</dd>
                            </div>
                        ))}
                        <div className="flex justify-between gap-4">
                            <dt className="text-app-text-muted">Estado</dt>
                            <dd>
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${tenant.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                    {tenant.activo ? 'Activo' : 'Inactivo'}
                                </span>
                            </dd>
                        </div>
                    </dl>
                </div>

                {/* Reuniones */}
                <div className="lg:col-span-3 bg-surface rounded-xl border border-surface-border overflow-hidden">
                    <div className="flex items-center justify-between px-6 py-4 border-b border-surface-border">
                        <h2 className="text-sm font-semibold text-app-text-primary">Reuniones</h2>
                        <a href={`/super-admin/tenants/${tenant.id}/reuniones/create`}
                            className="text-xs px-3 py-1.5 rounded-lg bg-brand text-white hover:opacity-90 transition">
                            + Nueva reunión
                        </a>
                    </div>
                    {reuniones.length === 0 ? (
                        <p className="px-6 py-4 text-sm text-app-text-muted">Sin reuniones aún.</p>
                    ) : (
                        <table className="w-full text-sm">
                            <thead className="bg-surface-hover text-app-text-muted text-xs uppercase">
                                <tr>
                                    <th className="px-4 py-2 text-left">Título</th>
                                    <th className="px-4 py-2 text-left">Estado</th>
                                    <th className="px-4 py-2 text-left">Fecha</th>
                                    <th className="px-4 py-2 text-left">Envíos</th>
                                    <th className="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-surface-border">
                                {reuniones.map(r => (
                                    <tr key={r.id}>
                                        <td className="px-4 py-3 font-medium text-app-text-primary">{r.titulo}</td>
                                        <td className="px-4 py-3 text-app-text-muted capitalize">{r.estado}</td>
                                        <td className="px-4 py-3 text-app-text-muted">{r.fecha_programada ? new Date(r.fecha_programada).toLocaleDateString('es-CO') : '—'}</td>
                                        <td className="px-4 py-3 text-app-text-muted">{r.convocatoria_envios}/2</td>
                                        <td className="px-4 py-3 text-right">
                                            {r.convocatoria_envios >= 2 && (
                                                <button
                                                    onClick={() => { if (confirm('¿Resetear contador de convocatorias?')) router.post(`/super-admin/reuniones/${r.id}/reset-convocatoria`, {}, { preserveScroll: true }) }}
                                                    className="text-xs px-2 py-1 rounded border border-warning/30 bg-warning-bg text-warning hover:bg-warning/20 transition"
                                                >
                                                    Reset convocatoria
                                                </button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>

                {/* Administradores */}
                <div className="lg:col-span-2 bg-surface rounded-xl border border-surface-border overflow-hidden">
                    <div className="px-5 py-4 border-b border-surface-border flex justify-between items-center">
                        <h2 className="text-sm font-semibold text-app-text-primary">Administradores</h2>
                        <button
                            onClick={() => setShowAddAdmin(!showAddAdmin)}
                            className="text-xs px-3 py-1.5 bg-brand hover:bg-brand-dark text-white rounded-lg transition-colors"
                        >
                            + Agregar admin
                        </button>
                    </div>

                    {showAddAdmin && (
                        <form onSubmit={submitAdmin} className="px-5 py-4 border-b border-surface-border bg-content-bg">
                            <p className="text-xs font-medium text-app-text-muted mb-3">Nuevo administrador</p>
                            <div className="grid sm:grid-cols-3 gap-3">
                                {[['nombre', 'Nombre'], ['email', 'Email'], ['password', 'Contraseña']].map(([key, label]) => (
                                    <div key={key}>
                                        <input
                                            type={key === 'password' ? 'password' : 'text'}
                                            placeholder={label}
                                            value={data[key]}
                                            onChange={e => setData(key, e.target.value)}
                                            className="w-full px-3 py-2 text-sm border border-surface-border rounded-lg bg-surface text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30"
                                        />
                                        {errors[key] && <p className="text-xs text-danger mt-1">{errors[key]}</p>}
                                    </div>
                                ))}
                            </div>
                            <div className="flex gap-2 mt-3">
                                <button type="submit" disabled={processing}
                                    className="text-xs px-3 py-1.5 bg-brand hover:bg-brand-dark text-white rounded-lg transition-colors disabled:opacity-50">
                                    Crear
                                </button>
                                <button type="button" onClick={() => { setShowAddAdmin(false); reset() }}
                                    className="text-xs px-3 py-1.5 border border-surface-border rounded-lg text-app-text-secondary hover:bg-surface-hover transition-colors">
                                    Cancelar
                                </button>
                            </div>
                        </form>
                    )}

                    {tenant.users.length === 0 ? (
                        <div className="px-5 py-10 text-center text-sm text-app-text-muted">
                            Sin administradores. Agrega el primero.
                        </div>
                    ) : (
                        <table className="w-full text-sm">
                            <tbody className="divide-y divide-surface-border">
                                {tenant.users.map(u => (
                                    <tr key={u.id} className="hover:bg-surface-hover transition-colors">
                                        <td className="px-5 py-3.5">
                                            <p className="font-medium text-app-text-primary">{u.name}</p>
                                            <p className="text-xs text-app-text-muted">{u.email}</p>
                                        </td>
                                        <td className="px-5 py-3.5">
                                            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${u.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                                {u.activo ? 'Activo' : 'Inactivo'}
                                            </span>
                                        </td>
                                        <td className="px-5 py-3.5 text-right">
                                            <button
                                                onClick={() => toggle(u.id)}
                                                className="text-xs text-app-text-muted hover:text-brand transition-colors"
                                            >
                                                {u.activo ? 'Desactivar' : 'Activar'}
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </AdminLayout>
    )
}
