import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, Link } from '@inertiajs/react'

export default function Edit({ tenant }) {
    const { data, setData, patch, processing, errors } = useForm({
        nombre: tenant.nombre,
        direccion: tenant.direccion ?? '',
        ciudad: tenant.ciudad ?? '',
        max_poderes_por_delegado: tenant.max_poderes_por_delegado,
        activo: tenant.activo,
    })

    const submit = (e) => {
        e.preventDefault()
        patch(`/super-admin/tenants/${tenant.id}`)
    }

    return (
        <AdminLayout title={`Editar — ${tenant.nombre}`}>
            <div className="max-w-xl">
                <form onSubmit={submit} className="bg-white rounded-lg shadow p-6 space-y-4">
                    {[
                        { key: 'nombre', label: 'Nombre *' },
                        { key: 'direccion', label: 'Dirección' },
                        { key: 'ciudad', label: 'Ciudad' },
                    ].map(f => (
                        <div key={f.key}>
                            <label className="block text-sm font-medium text-gray-700 mb-1">{f.label}</label>
                            <input
                                type="text"
                                value={data[f.key]}
                                onChange={e => setData(f.key, e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors[f.key] && <p className="text-red-500 text-xs mt-1">{errors[f.key]}</p>}
                        </div>
                    ))}

                    <div className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            id="activo"
                            checked={data.activo}
                            onChange={e => setData('activo', e.target.checked)}
                            className="rounded"
                        />
                        <label htmlFor="activo" className="text-sm text-gray-700">Activo</label>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
                            {processing ? 'Guardando...' : 'Guardar'}
                        </button>
                        <Link href={`/super-admin/tenants/${tenant.id}`} className="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
