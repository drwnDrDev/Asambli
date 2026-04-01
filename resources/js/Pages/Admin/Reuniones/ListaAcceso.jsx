import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage } from '@inertiajs/react'

export default function ListaAcceso({ reunion, accesos }) {
    const { flash } = usePage().props

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

            {accesos.length === 0 ? (
                <div className="bg-surface rounded-xl border border-surface-border p-8 text-center text-app-text-muted text-sm">
                    Sin accesos generados aún. Envía la convocatoria para generar PINs.
                </div>
            ) : (
                <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-surface-hover text-app-text-muted text-xs uppercase">
                            <tr>
                                <th className="px-4 py-3 text-left">Nombre / Documento</th>
                                <th className="px-4 py-3 text-left">Documento</th>
                                <th className="px-4 py-3 text-left">Unidad(es)</th>
                                <th className="px-4 py-3 text-center">PIN</th>
                                <th className="px-4 py-3 text-left">Tipo</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-surface-border">
                            {accesos.map(a => (
                                <tr key={a.id} className="hover:bg-surface-hover transition">
                                    <td className="px-4 py-3 font-medium text-app-text-primary">{a.nombre}</td>
                                    <td className="px-4 py-3 text-app-text-muted">{a.numero_documento}</td>
                                    <td className="px-4 py-3 text-app-text-muted">{a.unidades || '—'}</td>
                                    <td className="px-4 py-3 text-center font-mono font-bold text-brand tracking-widest">
                                        {a.pin ?? <span className="text-app-text-muted font-normal text-xs">no disponible</span>}
                                    </td>
                                    <td className="px-4 py-3">
                                        {a.es_externo
                                            ? <span className="inline-flex px-2 py-0.5 rounded-full text-xs bg-warning-bg text-warning">Delegado</span>
                                            : <span className="inline-flex px-2 py-0.5 rounded-full text-xs bg-surface-hover text-app-text-muted">Propietario</span>
                                        }
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <div className="px-4 py-3 border-t border-surface-border text-xs text-app-text-muted">
                        Total: {accesos.length} accesos activos · Envíos convocatoria: {reunion.convocatoria_envios}/2
                    </div>
                </div>
            )}
        </AdminLayout>
    )
}
