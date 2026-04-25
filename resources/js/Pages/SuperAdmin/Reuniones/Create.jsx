import AdminLayout from '@/Layouts/AdminLayout'
import { useForm } from '@inertiajs/react'

export default function Create({ tenant }) {
    const { data, setData, post, processing, errors } = useForm({
        titulo: '',
        tipo: 'asamblea',
        tipo_voto_peso: 'coeficiente',
        quorum_requerido: 51,
        fecha_programada: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post(`/super-admin/tenants/${tenant.id}/reuniones`)
    }

    return (
        <AdminLayout title={`Nueva Reunión — ${tenant.nombre}`}>
            <div className="max-w-xl">
                <p className="text-sm text-app-text-muted mb-4">Conjunto: <strong>{tenant.nombre}</strong></p>
                <form onSubmit={submit} className="bg-surface rounded-xl border border-surface-border p-6 space-y-5">
                    {/* titulo */}
                    <div>
                        <label className="block text-sm font-medium text-app-text-primary mb-1">Título *</label>
                        <input type="text" value={data.titulo} onChange={e => setData('titulo', e.target.value)}
                            className="w-full border border-surface-border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand"
                            placeholder="Ej: Asamblea Ordinaria 2026" />
                        {errors.titulo && <p className="text-danger text-xs mt-1">{errors.titulo}</p>}
                    </div>
                    {/* tipo (solo asamblea) */}
                    <div>
                        <label className="block text-sm font-medium text-app-text-primary mb-1">Tipo *</label>
                        <select value={data.tipo} onChange={e => setData('tipo', e.target.value)}
                            className="w-full border border-surface-border rounded-lg px-3 py-2 text-sm">
                            <option value="asamblea">Asamblea</option>
                        </select>
                    </div>
                    {/* tipo_voto_peso */}
                    <div>
                        <label className="block text-sm font-medium text-app-text-primary mb-1">Sistema de voto *</label>
                        <select value={data.tipo_voto_peso} onChange={e => setData('tipo_voto_peso', e.target.value)}
                            className="w-full border border-surface-border rounded-lg px-3 py-2 text-sm">
                            <option value="coeficiente">Por coeficiente de propiedad</option>
                            <option value="unidad">Por unidad (1 unidad = 1 voto)</option>
                        </select>
                    </div>
                    {/* quorum */}
                    <div>
                        <label className="block text-sm font-medium text-app-text-primary mb-1">Quórum requerido (%) *</label>
                        <input type="number" min="0" max="100" value={data.quorum_requerido}
                            onChange={e => setData('quorum_requerido', e.target.value)}
                            className="w-full border border-surface-border rounded-lg px-3 py-2 text-sm" />
                        {errors.quorum_requerido && <p className="text-danger text-xs mt-1">{errors.quorum_requerido}</p>}
                    </div>
                    {/* fecha */}
                    <div>
                        <label className="block text-sm font-medium text-app-text-primary mb-1">Fecha programada</label>
                        <input type="datetime-local" value={data.fecha_programada}
                            onChange={e => setData('fecha_programada', e.target.value)}
                            className="w-full border border-surface-border rounded-lg px-3 py-2 text-sm" />
                    </div>
                    <div className="flex gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="bg-brand text-white px-5 py-2 rounded-lg text-sm font-medium hover:opacity-90 disabled:opacity-50 transition">
                            {processing ? 'Creando...' : 'Crear reunión'}
                        </button>
                        <a href={`/super-admin/tenants/${tenant.id}`}
                            className="px-5 py-2 rounded-lg text-sm text-app-text-muted hover:bg-surface-hover transition">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
