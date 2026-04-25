import AdminLayout from '@/Layouts/AdminLayout'
import { Link, useForm } from '@inertiajs/react'

const TIPOS_DOC = ['CC', 'CE', 'NIT', 'PP', 'TI', 'PEP']

export default function Edit({ copropietario, unidades = [] }) {
    const asignadasIds = (copropietario.unidades ?? []).map(u => u.id)

    const { data, setData, patch, processing, errors } = useForm({
        nombre: copropietario.nombre ?? '',
        email: copropietario.email ?? '',
        tipo_documento: copropietario.tipo_documento ?? '',
        numero_documento: copropietario.numero_documento ?? '',
        telefono: copropietario.telefono ?? '',
        es_residente: copropietario.es_residente ?? false,
        activo: copropietario.activo ?? true,
        unidades: asignadasIds,
    })

    const submit = (e) => {
        e.preventDefault()
        patch(`/admin/copropietarios/${copropietario.id}`)
    }

    const toggleUnidad = (id) => {
        setData('unidades', data.unidades.includes(id)
            ? data.unidades.filter(u => u !== id)
            : [...data.unidades, id])
    }

    const inputClass = "w-full px-3.5 py-2.5 rounded-lg border border-surface-border bg-surface text-app-text-primary text-sm focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
    const labelClass = "block text-sm font-medium text-app-text-secondary mb-1.5"
    const errorClass = "mt-1 text-xs text-danger"

    return (
        <AdminLayout title={`Editar — ${copropietario.nombre}`}>
            <div className="mb-5">
                <Link href={`/admin/copropietarios/${copropietario.id}`} className="text-sm text-app-text-muted hover:text-brand transition-colors">
                    ← {copropietario.nombre}
                </Link>
            </div>

            <div className="max-w-lg bg-surface rounded-xl border border-surface-border p-6">
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className={labelClass}>Nombre completo</label>
                        <input type="text" value={data.nombre} onChange={e => setData('nombre', e.target.value)} className={inputClass} />
                        {errors.nombre && <p className={errorClass}>{errors.nombre}</p>}
                    </div>

                    <div>
                        <label className={labelClass}>Correo electrónico</label>
                        <input type="email" value={data.email} onChange={e => setData('email', e.target.value)} className={inputClass} />
                        {errors.email && <p className={errorClass}>{errors.email}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className={labelClass}>Tipo documento</label>
                            <select value={data.tipo_documento} onChange={e => setData('tipo_documento', e.target.value)} className={inputClass}>
                                <option value="">Seleccionar...</option>
                                {TIPOS_DOC.map(t => <option key={t} value={t}>{t}</option>)}
                            </select>
                            {errors.tipo_documento && <p className={errorClass}>{errors.tipo_documento}</p>}
                        </div>
                        <div>
                            <label className={labelClass}>Número documento</label>
                            <input type="text" value={data.numero_documento} onChange={e => setData('numero_documento', e.target.value)} className={inputClass} />
                            {errors.numero_documento && <p className={errorClass}>{errors.numero_documento}</p>}
                        </div>
                    </div>

                    <div>
                        <label className={labelClass}>Teléfono</label>
                        <input type="text" value={data.telefono} onChange={e => setData('telefono', e.target.value)} className={inputClass} />
                        {errors.telefono && <p className={errorClass}>{errors.telefono}</p>}
                    </div>

                    {unidades.length > 0 && (
                        <div>
                            <label className={labelClass}>Unidades asignadas</label>
                            <div className="space-y-1.5 max-h-48 overflow-y-auto border border-surface-border rounded-lg p-3">
                                {unidades.map(u => (
                                    <label key={u.id} className="flex items-center gap-2.5 cursor-pointer py-0.5">
                                        <input type="checkbox"
                                            checked={data.unidades.includes(u.id)}
                                            onChange={() => toggleUnidad(u.id)}
                                            className="w-4 h-4 accent-brand rounded" />
                                        <span className="text-sm text-app-text-secondary">
                                            {u.numero} — {u.tipo} <span className="font-mono text-xs">({u.coeficiente}%)</span>
                                        </span>
                                    </label>
                                ))}
                            </div>
                            {errors.unidades && <p className={errorClass}>{errors.unidades}</p>}
                        </div>
                    )}

                    <div className="flex gap-5">
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.es_residente}
                                onChange={e => setData('es_residente', e.target.checked)}
                                className="w-4 h-4 accent-brand rounded" />
                            <span className="text-sm text-app-text-secondary">Es residente</span>
                        </label>
                        <label className="flex items-center gap-2.5 cursor-pointer">
                            <input type="checkbox" checked={data.activo}
                                onChange={e => setData('activo', e.target.checked)}
                                className="w-4 h-4 accent-brand rounded" />
                            <span className="text-sm text-app-text-secondary">Activo</span>
                        </label>
                    </div>

                    <div className="flex items-center gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-60">
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                        <Link href={`/admin/copropietarios/${copropietario.id}`}
                            className="px-5 py-2.5 border border-surface-border text-sm font-medium text-app-text-secondary hover:text-app-text-primary rounded-lg transition-colors">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
