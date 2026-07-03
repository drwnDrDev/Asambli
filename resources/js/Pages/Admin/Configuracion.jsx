import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, usePage } from '@inertiajs/react'

export default function Configuracion({ tenant }) {
    const { flash } = usePage().props
    const { data, setData, patch, processing, errors } = useForm({
        nombre:                   tenant.nombre ?? '',
        direccion:                tenant.direccion ?? '',
        ciudad:                   tenant.ciudad ?? '',
        max_poderes_por_delegado: tenant.max_poderes_por_delegado ?? 2,
        restringir_voto_morosos:  tenant.restringir_voto_morosos ?? true,
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

                    <div className="flex items-center justify-between py-3 border-b border-surface-border">
                        <div>
                            <p className="text-sm font-medium text-gray-900">Restringir voto de morosos</p>
                            <p className="text-xs text-gray-500">
                                Impide votar a copropietarios marcados como "en mora" (Art. 38 Ley 675 de 2001)
                            </p>
                        </div>
                        <label className="relative inline-flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                checked={data.restringir_voto_morosos}
                                onChange={e => setData('restringir_voto_morosos', e.target.checked)}
                                className="sr-only peer"
                            />
                            <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-indigo-300 rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600" />
                        </label>
                    </div>

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
