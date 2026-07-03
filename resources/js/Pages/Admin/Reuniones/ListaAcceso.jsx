import AdminLayout from '@/Layouts/AdminLayout'
import { Link, router, usePage } from '@inertiajs/react'
import { useState } from 'react'

export default function ListaAcceso({ reunion, accesos, filtro = '' }) {
    const { flash } = usePage().props
    const [procesando, setProcesando] = useState(null)
    const [q, setQ] = useState(filtro)
    const [pinesVisibles, setPinesVisibles] = useState({})

    const buscar = (valor) => {
        setQ(valor)
        clearTimeout(window.__laTimer)
        window.__laTimer = setTimeout(() => {
            router.get(`/admin/reuniones/${reunion.id}/lista-acceso`, valor ? { q: valor } : {}, {
                preserveState: true, preserveScroll: true, replace: true,
            })
        }, 300)
    }

    const togglePin = (id) => setPinesVisibles(prev => ({ ...prev, [id]: !prev[id] }))

    const reenviar = (acceso) => {
        if (!confirm(`¿Regenerar PIN y reenviar acceso a ${acceso.email}?`)) return
        setProcesando(acceso.id)
        router.post(`/admin/reuniones/${reunion.id}/lista-acceso/${acceso.id}/reenviar`, {}, {
            preserveScroll: true,
            onFinish: () => setProcesando(null),
        })
    }

    const toggleActivo = (acceso) => {
        const accion = acceso.activo ? 'desactivar' : 'activar'
        const mensaje = acceso.activo
            ? `¿Desactivar acceso de ${acceso.nombre}? Su sesión activa quedará invalidada.`
            : `¿Reactivar acceso de ${acceso.nombre}?`
        if (!confirm(mensaje)) return
        setProcesando(acceso.id)
        router.patch(`/admin/reuniones/${reunion.id}/lista-acceso/${acceso.id}/${accion}`, {}, {
            preserveScroll: true,
            onFinish: () => setProcesando(null),
        })
    }

    return (
        <AdminLayout title={`Lista de Acceso — ${reunion.titulo}`}>
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <div className="mb-4 flex items-center gap-3">
                <Link href={`/admin/reuniones/${reunion.id}`}
                    className="text-sm text-app-text-muted hover:text-brand">← Volver</Link>
                <h1 className="text-lg font-semibold text-app-text-primary">{reunion.titulo}</h1>
                <button
                    onClick={() => window.print()}
                    className="ml-auto text-sm px-3 py-1.5 rounded-lg border border-surface-border bg-surface hover:bg-surface-hover transition text-app-text-secondary">
                    Imprimir
                </button>
            </div>

            <input
                type="text"
                value={q}
                onChange={e => buscar(e.target.value)}
                placeholder="Buscar por nombre, documento, email o unidad…"
                className="w-full sm:w-80 border border-gray-300 rounded-lg px-3 py-2 text-sm mb-4 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />

            {accesos.data.length === 0 ? (
                <div className="bg-surface rounded-xl border border-surface-border p-8 text-center text-app-text-muted text-sm">
                    {q ? 'Sin resultados para la búsqueda.' : 'Sin accesos generados aún. Envía la convocatoria para generar PINs.'}
                </div>
            ) : (
                <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                    <div className="px-4 py-3 border-b border-surface-border flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-app-text-primary">
                            Accesos
                            <span className="ml-2 text-xs font-normal text-app-text-muted">({accesos.total} total)</span>
                        </h2>
                        <span className="text-xs text-app-text-muted">Envíos convocatoria: {reunion.convocatoria_envios}/2</span>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="bg-surface-hover text-app-text-muted text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Nombre / Email</th>
                                <th className="px-4 py-3 text-left">Documento</th>
                                <th className="px-4 py-3 text-left">Unidad(es)</th>
                                <th className="px-4 py-3 text-center">PIN</th>
                                <th className="px-4 py-3 text-left">Tipo</th>
                                <th className="px-4 py-3 text-left">Estado</th>
                                <th className="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-surface-border">
                            {accesos.data.map(a => (
                                <tr key={a.id} className={`hover:bg-surface-hover transition ${!a.activo ? 'opacity-60' : ''}`}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-app-text-primary">{a.nombre}</div>
                                        {a.email && a.email !== a.nombre && (
                                            <div className="text-xs text-app-text-muted">{a.email}</div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-app-text-muted">{a.numero_documento}</td>
                                    <td className="px-4 py-3 text-app-text-muted">{a.unidades || '—'}</td>
                                    <td className="px-4 py-3 text-center font-mono">
                                        {a.pin ? (
                                            <button
                                                type="button"
                                                onClick={() => togglePin(a.id)}
                                                title={pinesVisibles[a.id] ? 'Ocultar PIN' : 'Mostrar PIN'}
                                                className="tracking-widest text-gray-700 hover:text-blue-600"
                                            >
                                                {pinesVisibles[a.id] ? a.pin : '••••••'}
                                            </button>
                                        ) : (
                                            <span className="text-app-text-muted font-normal text-xs">—</span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {a.es_externo
                                            ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs bg-warning-bg text-warning">Delegado</span>
                                            : <span className="inline-flex px-2 py-0.5 rounded-full text-xs bg-surface-hover text-app-text-muted">Propietario</span>
                                        }
                                    </td>
                                    <td className="px-4 py-3">
                                        {a.activo
                                            ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs bg-success-bg text-success">Activo</span>
                                            : <span className="inline-flex px-2 py-0.5 rounded-full text-xs bg-surface-hover text-app-text-muted">Inactivo</span>
                                        }
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {a.email && a.activo && (
                                                <button
                                                    onClick={() => reenviar(a)}
                                                    disabled={procesando === a.id}
                                                    className="text-xs px-2 py-1 rounded border border-brand/30 text-brand hover:bg-brand/10 disabled:opacity-40 transition"
                                                >
                                                    {procesando === a.id ? '…' : 'Re-enviar'}
                                                </button>
                                            )}
                                            <button
                                                onClick={() => toggleActivo(a)}
                                                disabled={procesando === a.id}
                                                className={`text-xs px-2 py-1 rounded border disabled:opacity-40 transition ${
                                                    a.activo
                                                        ? 'border-danger/30 text-danger hover:bg-danger/10'
                                                        : 'border-success/30 text-success hover:bg-success/10'
                                                }`}
                                            >
                                                {a.activo ? 'Desactivar' : 'Reactivar'}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>

                    {accesos.links?.length > 3 && (
                        <div className="px-4 py-3 border-t border-gray-100 flex flex-wrap gap-1">
                            {accesos.links.map((l, i) => (
                                <Link
                                    key={i}
                                    href={l.url ?? '#'}
                                    preserveScroll
                                    preserveState
                                    className={`px-2.5 py-1 rounded text-xs ${l.active ? 'bg-blue-600 text-white' : l.url ? 'text-gray-600 hover:bg-gray-100' : 'text-gray-300 pointer-events-none'}`}
                                    dangerouslySetInnerHTML={{ __html: l.label }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            )}
        </AdminLayout>
    )
}
