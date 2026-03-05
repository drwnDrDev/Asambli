import AdminLayout from '@/Layouts/AdminLayout'
import { useForm } from '@inertiajs/react'

export default function Create() {
    const { data, setData, post, processing, errors } = useForm({
        titulo: '',
        tipo: 'asamblea',
        tipo_voto_peso: 'coeficiente',
        quorum_requerido: 51,
        fecha_programada: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post('/admin/reuniones')
    }

    return (
        <AdminLayout title="Nueva Reunión">
            <div className="max-w-xl">
                <form onSubmit={submit} className="bg-white rounded-lg shadow p-6 space-y-5">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                        <input
                            type="text"
                            value={data.titulo}
                            onChange={e => setData('titulo', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Ej: Asamblea Ordinaria 2026"
                        />
                        {errors.titulo && <p className="text-red-500 text-xs mt-1">{errors.titulo}</p>}
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Tipo *</label>
                        <select
                            value={data.tipo}
                            onChange={e => setData('tipo', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="asamblea">Asamblea</option>
                            <option value="consejo">Consejo</option>
                            <option value="extraordinaria">Extraordinaria</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">Sistema de voto *</label>
                        <select
                            value={data.tipo_voto_peso}
                            onChange={e => setData('tipo_voto_peso', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        >
                            <option value="coeficiente">Por coeficiente de propiedad</option>
                            <option value="unidad">Por unidad (1 unidad = 1 voto)</option>
                        </select>
                    </div>

                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-1">
                            Quórum requerido (%) *
                        </label>
                        <input
                            type="number"
                            min="1"
                            max="100"
                            value={data.quorum_requerido}
                            onChange={e => setData('quorum_requerido', e.target.value)}
                            className="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        {errors.quorum_requerido && <p className="text-red-500 text-xs mt-1">{errors.quorum_requerido}</p>}
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
                            {processing ? 'Creando...' : 'Crear reunión'}
                        </button>
                        <a href="/admin/reuniones" className="px-5 py-2 rounded-lg text-sm text-gray-600 hover:bg-gray-100 transition">
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </AdminLayout>
    )
}
