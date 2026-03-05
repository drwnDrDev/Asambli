import AdminLayout from '@/Layouts/AdminLayout'
import { Link } from '@inertiajs/react'

export default function Dashboard() {
    return (
        <AdminLayout title="Dashboard">
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link
                    href="/admin/reuniones"
                    className="bg-white rounded-lg shadow p-6 hover:shadow-md transition group"
                >
                    <div className="text-3xl mb-3">📋</div>
                    <h2 className="font-semibold text-gray-900 group-hover:text-blue-700 transition">Reuniones</h2>
                    <p className="text-sm text-gray-500 mt-1">Crear y gestionar asambleas</p>
                </Link>

                <Link
                    href="/admin/padron"
                    className="bg-white rounded-lg shadow p-6 hover:shadow-md transition group"
                >
                    <div className="text-3xl mb-3">👥</div>
                    <h2 className="font-semibold text-gray-900 group-hover:text-blue-700 transition">Padrón</h2>
                    <p className="text-sm text-gray-500 mt-1">Importar copropietarios desde CSV</p>
                </Link>

                <Link
                    href="/sala"
                    className="bg-white rounded-lg shadow p-6 hover:shadow-md transition group"
                >
                    <div className="text-3xl mb-3">🗳️</div>
                    <h2 className="font-semibold text-gray-900 group-hover:text-blue-700 transition">Sala de Reunión</h2>
                    <p className="text-sm text-gray-500 mt-1">Vista del copropietario</p>
                </Link>
            </div>
        </AdminLayout>
    )
}
