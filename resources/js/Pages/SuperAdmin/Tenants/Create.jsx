import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, Link } from '@inertiajs/react'

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        nombre: '',
        nit: '',
        direccion: '',
        ciudad: '',
        max_poderes_por_delegado: 2,
    })

    const submit = (e) => {
        e.preventDefault()
        post('/super-admin/tenants')
    }

    return (
        <AdminLayout title="Nuevo Conjunto">
            <div className="max-w-xl">
                <form onSubmit={submit} className="bg-white rounded-lg shadow p-6 space-y-4">
                    {[
                        { key: 'nombre', label: 'Nombre del conjunto *', type: 'text' },
                        { key: 'nit', label: 'NIT *', type: 'text' },
                        { key: 'direccion', label: 'Dirección', type: 'text' },
                        { key: 'ciudad', label: 'Ciudad', type: 'text' },
                    ].map(f => (
                        <div key={f.key}>
                            <label className="block text-sm font-medium text-gray-700 mb-1">{f.label}</label>
                            <input
                                type={f.type}
                                value={data[f.key]}
                                onChange={e => setData(f.key, e.target.value)}
                                className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors[f.key] && <p className="text-red-500 text-xs mt-1">{errors[f.key]}</p>}
                        </div>
                    ))}

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Máx. poderes por delegado
                        </label>
                        <input
                            type="number"
                            min="1"
                            max="10"
                            value={data.max_poderes_por_delegado}
                            onChange={e => setData('max_poderes_por_delegado', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button type="submit" disabled={processing}
                            className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition">
                            {processing ? 'Creando...' : 'Crear conjunto'}
                        </button>
                        <Link href="/super-admin/tenants" className="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
