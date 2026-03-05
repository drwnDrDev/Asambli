import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, Link } from '@inertiajs/react'

export default function Edit({ reunion }) {
    const { data, setData, patch, processing, errors } = useForm({
        titulo: reunion.titulo,
        fecha_programada: reunion.fecha_programada ?? '',
    })

    const submit = (e) => {
        e.preventDefault()
        patch(`/admin/reuniones/${reunion.id}`)
    }

    return (
        <AdminLayout title="Editar Reunión">
            <div className="max-w-xl">
                <form onSubmit={submit} className="bg-white rounded-lg shadow p-6 space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                        <input
                            type="text"
                            value={data.titulo}
                            onChange={e => setData('titulo', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        {errors.titulo && <p className="text-red-500 text-xs mt-1">{errors.titulo}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Fecha programada</label>
                        <input
                            type="datetime-local"
                            value={data.fecha_programada}
                            onChange={e => setData('fecha_programada', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition"
                        >
                            {processing ? 'Guardando...' : 'Guardar cambios'}
                        </button>
                        <Link href={`/admin/reuniones/${reunion.id}`} className="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">
                            Cancelar
                        </Link>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
