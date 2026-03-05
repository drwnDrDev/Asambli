import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage } from '@inertiajs/react'

export default function Index({ tenants = [] }) {
    const { flash } = usePage().props

    return (
        <AdminLayout title="Conjuntos (Super Admin)">
            <div className="flex justify-end mb-4">
                <Link href="/super-admin/tenants/create"
                    className="bg-blue-600 text-white text-sm px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                    + Nuevo conjunto
                </Link>
            </div>

            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            <div className="bg-white rounded-lg shadow overflow-hidden">
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Nombre</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">NIT</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Ciudad</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Estado</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {tenants.map(t => (
                            <tr key={t.id} className="border-b hover:bg-gray-50">
                                <td className="px-4 py-3 font-medium">{t.nombre}</td>
                                <td className="px-4 py-3 text-gray-500">{t.nit}</td>
                                <td className="px-4 py-3 text-gray-500">{t.ciudad ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <span className={`px-2 py-0.5 rounded-full text-xs ${t.activo ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-600'}`}>
                                        {t.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right">
                                    <Link href={`/super-admin/tenants/${t.id}`} className="text-blue-600 hover:underline">
                                        Ver →
                                    </Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </AdminLayout>
    )
}
