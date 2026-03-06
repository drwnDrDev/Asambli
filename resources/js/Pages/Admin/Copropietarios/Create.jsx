import AdminLayout from '@/Layouts/AdminLayout'
import { Link, useForm } from '@inertiajs/react'

export default function Create({ unidades = [] }) {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        email: '',
        telefono: '',
        es_residente: false,
        unidad_id: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post('/admin/copropietarios')
    }

    const inputClass = "w-full px-3.5 py-2.5 rounded-lg border border-surface-border bg-surface text-app-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
    const labelClass = "block text-sm font-medium text-app-text-secondary mb-1.5"
    const errorClass = "mt-1 text-xs text-danger"

    return (
        <AdminLayout title="Nuevo Copropietario">
            <div className="mb-5">
                <Link href="/admin/copropietarios" className="text-sm text-app-text-muted hover:text-brand transition-colors">
                    ← Copropietarios
                </Link>
            </div>

            <div className="max-w-lg bg-surface rounded-xl border border-surface-border p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className={labelClass}>Nombre completo</label>
                        <input type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)}
                            className={inputClass} placeholder="Juan Pérez" autoFocus />
                        {errors.nombre && <p className={errorClass}>{errors.nombre}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Correo electrónico</label>
                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)}
                            className={inputClass} placeholder="juan@ejemplo.com" />
                        {errors.email && <p className={errorClass}>{errors.email}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Teléfono <span className="text-app-text-muted font-normal">(opcional)</span></label>
                        <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)}
                            className={inputClass} placeholder="+57 300 000 0000" />
                        {errors.telefono && <p className={errorClass}>{errors.telefono}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Unidad</label>
                        <select value={data.unidad_id} onChange={e => setData('unidad_id', e.target.value)}
                            className={inputClass}>
                            <option value="">Seleccionar unidad...</option>
                            {unidades.map(u => (
                                <option key={u.id} value={u.id}>
                                    {u.numero} — {u.tipo} ({u.coeficiente}%)
                                </option>
                            ))}
                        </select>
                        {errors.unidad_id && <p className={errorClass}>{errors.unidad_id}</p>}
                    </div>

                    <div>
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.es_residente}
                                onChange={e => setData('es_residente', e.target.checked)}
                                className="w-4 h-4 accent-brand rounded" />
                            <span className="text-sm text-app-text-secondary">Es residente</span>
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-60">
                            {processing ? 'Guardando...' : 'Crear copropietario'}
                        </button>
                        <Link href="/admin/copropietarios"
                            className="px-5 py-2.5 border border-surface-border text-sm font-medium text-app-text-secondary hover:text-app-text-primary rounded-lg transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
