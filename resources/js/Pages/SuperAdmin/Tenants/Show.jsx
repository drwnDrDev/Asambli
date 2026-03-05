import AdminLayout from '@/Layouts/AdminLayout'
import { Link, router } from '@inertiajs/react'

export default function Show({ tenant }) {
    return (
        <AdminLayout title={tenant.nombre}>
            <div className="mb-4 flex gap-3">
                <Link href="/super-admin/tenants" className="text-sm text-blue-600 hover:underline">← Lista</Link>
                <Link href={`/super-admin/tenants/${tenant.id}/edit`}
                    className="text-sm bg-gray-100 px-3 py-1 rounded hover:bg-gray-200 transition">
                    Editar
                </Link>
                <button
                    onClick={() => {
                        if (confirm(`¿Desactivar "${tenant.nombre}"?`))
                            router.delete(`/super-admin/tenants/${tenant.id}`)
                    }}
                    className="text-sm bg-red-50 text-red-600 px-3 py-1 rounded hover:bg-red-100 transition ml-auto"
                >
                    Desactivar
                </button>
            </div>

            <div className="bg-white rounded-lg shadow p-6 max-w-lg space-y-3">
                {[
                    ['Nombre', tenant.nombre],
                    ['NIT', tenant.nit],
                    ['Dirección', tenant.direccion ?? '—'],
                    ['Ciudad', tenant.ciudad ?? '—'],
                    ['Máx. poderes', tenant.max_poderes_por_delegado],
                    ['Estado', tenant.activo ? 'Activo' : 'Inactivo'],
                ].map(([label, value]) => (
                    <div key={label} className="flex gap-4 text-sm border-b border-gray-50 pb-2">
                        <span className="font-medium text-gray-500 w-36">{label}</span>
                        <span>{value}</span>
                    </div>
                ))}
            </div>
        </AdminLayout>
    )
}
