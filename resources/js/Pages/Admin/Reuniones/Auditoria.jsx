import AdminLayout from '@/Layouts/AdminLayout'
import { Link } from '@inertiajs/react'

export default function Auditoria({ reunion, logs = [] }) {
    return (
        <AdminLayout title={`Auditoría — ${reunion.titulo}`}>
            <div className="mb-4">
                <Link href={`/admin/reuniones/${reunion.id}`} className="text-sm text-blue-600 hover:underline">
                    ← Volver a la reunión
                </Link>
            </div>

            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Fecha/Hora</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Acción</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Usuario</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        {logs.map((log, i) => (
                            <tr key={i} className="border-b border-gray-50">
                                <td className="px-4 py-3 text-gray-500 whitespace-nowrap">{log.created_at}</td>
                                <td className="px-4 py-3 font-medium">{log.accion}</td>
                                <td className="px-4 py-3 text-gray-500">{log.user?.name ?? '—'}</td>
                                <td className="px-4 py-3 text-gray-400 text-xs">
                                    {log.metadata ? JSON.stringify(log.metadata) : ''}
                                </td>
                            </tr>
                        ))}
                        {logs.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-gray-400">
                                    No hay eventos registrados.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    )
}
