import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, usePage } from '@inertiajs/react'

export default function Configuracion({ tenant }) {
    const { flash } = usePage().props
    const { data, setData, patch, processing, errors } = useForm({
        nombre:                   tenant.nombre ?? '',
        direccion:                tenant.direccion ?? '',
        ciudad:                   tenant.ciudad ?? '',
        max_poderes_por_delegado: tenant.max_poderes_por_delegado ?? 2,
    })

    const submit = (e) => {
        e.preventDefault()
        patch('/admin/configuracion')
    }

    const Field = ({ label, name, type = 'text', ...props }) => (
        <div>
            <label className="block text-xs font-medium text-app-text-muted mb-1">{label}</label>
            <input
                type={type}
                value={data[name]}
                onChange={e => setData(name, type === 'number' ? parseInt(e.target.value) : e.target.value)}
                className="w-full px-3 py-2 text-sm border border-surface-border rounded-lg bg-content-bg text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30"
                {...props}
            />
            {errors[name] && <p className="text-xs text-danger mt-1">{errors[name]}</p>}
        </div>
    )

    return (
        <AdminLayout title="Configuración del conjunto">
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}

            <form onSubmit={submit} className="max-w-lg">
                <div className="bg-surface rounded-xl border border-surface-border p-6 space-y-4">
                    <Field label="Nombre del conjunto *" name="nombre" required />
                    <Field label="Dirección" name="direccion" />
                    <Field label="Ciudad" name="ciudad" />
                    <Field label="Máx. poderes por delegado" name="max_poderes_por_delegado" type="number" min={1} max={10} />

                    <div className="pt-2">
                        <p className="text-xs text-app-text-muted">NIT: <span className="font-mono font-medium text-app-text-secondary">{tenant.nit}</span> (no editable)</p>
                    </div>
                </div>

                <div className="mt-5">
                    <button type="submit" disabled={processing}
                        className="px-5 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors disabled:opacity-50">
                        {processing ? 'Guardando...' : 'Guardar cambios'}
                    </button>
                </div>
            </form>
        </AdminLayout>
    )
}
