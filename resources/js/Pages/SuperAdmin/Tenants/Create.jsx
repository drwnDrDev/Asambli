import AdminLayout from '@/Layouts/AdminLayout'
import { useForm } from '@inertiajs/react'

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '', nit: '', direccion: '', ciudad: '',
        max_poderes_por_delegado: 2,
        admin_nombre: '', admin_email: '', admin_password: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post('/super-admin/tenants')
    }

    const field = (label, key, props = {}) => (
        <div>
            <label className="block text-xs font-medium text-app-text-muted mb-1">{label}</label>
            <input
                {...props}
                value={data[key]}
                onChange={e => setData(key, e.target.value)}
                className="w-full px-3 py-2 text-sm border border-surface-border rounded-lg bg-content-bg text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30"
            />
            {errors[key] && <p className="text-xs text-danger mt-1">{errors[key]}</p>}
        </div>
    )

    return (
        <AdminLayout title="Nuevo conjunto">
            <form onSubmit={submit} className="max-w-lg space-y-6">
                <div className="bg-surface rounded-xl border border-surface-border p-6 space-y-4">
                    <h2 className="text-sm font-semibold text-app-text-primary">Datos del conjunto</h2>
                    {field('Nombre *', 'nombre', { required: true })}
                    {field('NIT *', 'nit', { required: true })}
                    {field('Dirección', 'direccion')}
                    {field('Ciudad', 'ciudad')}
                    <div>
                        <label className="block text-xs font-medium text-app-text-muted mb-1">Máx. poderes por delegado</label>
                        <input
                            type="number" min={1} max={10}
                            value={data.max_poderes_por_delegado}
                            onChange={e => setData('max_poderes_por_delegado', parseInt(e.target.value))}
                            className="w-24 px-3 py-2 text-sm border border-surface-border rounded-lg bg-content-bg text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30"
                        />
                    </div>
                </div>

                <div className="bg-surface rounded-xl border border-surface-border p-6 space-y-4">
                    <div>
                        <h2 className="text-sm font-semibold text-app-text-primary">Admin del conjunto</h2>
                        <p className="text-xs text-app-text-muted mt-0.5">Opcional — se puede agregar después.</p>
                    </div>
                    {field('Nombre del admin', 'admin_nombre')}
                    {field('Email del admin', 'admin_email', { type: 'email' })}
                    {field('Contraseña', 'admin_password', { type: 'password' })}
                </div>

                <div className="flex gap-3">
                    <button
                        type="submit" disabled={processing}
                        className="px-5 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50"
                    >
                        {processing ? 'Creando...' : 'Crear conjunto'}
                    </button>
                    <a href="/super-admin/tenants" className="px-5 py-2 text-sm text-app-text-secondary hover:text-app-text-primary rounded-lg border border-surface-border transition-colors">
                        Cancelar
                    </a>
                </div>
            </form>
        </AdminLayout>
    )
}
