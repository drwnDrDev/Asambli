import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage } from '@inertiajs/react'

const ESTADO_BADGE = {
    borrador:   'bg-gray-100 text-gray-600',
    convocada:  'bg-blue-100 text-blue-700',
    en_curso:   'bg-green-100 text-green-700',
    finalizada: 'bg-slate-100 text-slate-500',
}

export default function Index({ reuniones = [] }) {
    const { flash } = usePage().props

    return (
        <AdminLayout title="Reuniones">
            <div className="flex justify-between items-center mb-6">
                <p className="text-sm text-gray-500">{reuniones.length} reunión(es)</p>
                <Link
                    href="/admin/reuniones/create"
                    className="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 transition"
                >
                    + Nueva reunión
                </Link>
            </div>

            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            {reuniones.length === 0 ? (
                <div className="bg-white rounded-lg shadow p-12 text-center text-gray-400">
                    <div className="text-4xl mb-3">📋</div>
                    <p>No hay reuniones creadas aún.</p>
                    <Link href="/admin/reuniones/create" className="mt-4 inline-block text-blue-600 text-sm hover:underline">
                        Crear la primera reunión
                    </Link>
                </div>
            ) : (
                <div className="bg-white rounded-lg shadow overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 border-b border-gray-200">
                            <tr>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Título</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Tipo</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Estado</th>
                                <th className="text-left px-4 py-3 font-medium text-gray-600">Fecha</th>
                                <th className="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            {reuniones.map(r => (
                                <tr key={r.id} className="border-b border-gray-100 hover:bg-gray-50">
                                    <td className="px-4 py-3 font-medium text-gray-900">{r.titulo}</td>
                                    <td className="px-4 py-3 text-gray-500 capitalize">{r.tipo}</td>
                                    <td className="px-4 py-3">
                                        <span className={`px-2 py-1 rounded-full text-xs font-medium ${ESTADO_BADGE[r.estado] || 'bg-gray-100 text-gray-600'}`}>
                                            {r.estado}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-gray-500">
                                        {r.fecha_programada ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-4">
                                            <Link
                                                href="/admin/poderes"
                                                className="text-gray-400 hover:text-blue-600 text-xs"
                                            >
                                                Poderes
                                            </Link>
                                            <Link
                                                href={`/admin/reuniones/${r.id}`}
                                                className="text-blue-600 hover:underline"
                                            >
                                                Ver →
                                            </Link>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </AdminLayout>
    )
}
