import AdminLayout from '@/Layouts/AdminLayout'
import { Link } from '@inertiajs/react'

export default function Resultados({ votacion }) {
    const total = votacion.opciones?.reduce((sum, o) => sum + (o.votos?.length ?? 0), 0) ?? 0

    return (
        <AdminLayout title={`Resultados — ${votacion.pregunta}`}>
            <div className="mb-4">
                <Link href={`/admin/reuniones/${votacion.reunion?.id}/conducir`} className="text-sm text-blue-600 hover:underline">
                    ← Volver a conducción
                </Link>
            </div>

            <div className="max-w-xl bg-white rounded-lg shadow p-6">
                <p className="text-xs text-gray-400 uppercase mb-1">Votación</p>
                <h2 className="text-lg font-semibold mb-4">{votacion.pregunta}</h2>

                <div className="space-y-3">
                    {votacion.opciones?.map(opcion => {
                        const count = opcion.votos?.length ?? 0
                        const pct = total > 0 ? Math.round((count / total) * 100) : 0
                        return (
                            <div key={opcion.id}>
                                <div className="flex justify-between text-sm mb-1">
                                    <span className="font-medium">{opcion.texto}</span>
                                    <span className="text-gray-500">{count} votos ({pct}%)</span>
                                </div>
                                <div className="h-3 bg-gray-100 rounded-full overflow-hidden">
                                    <div
                                        className="h-full bg-blue-500 rounded-full transition-all"
                                        style={{ width: `${pct}%` }}
                                    />
                                </div>
                            </div>
                        )
                    })}
                </div>

                <p className="text-xs text-gray-400 mt-4">Total: {total} votos · Estado: {votacion.estado}</p>
            </div>
        </AdminLayout>
    )
}
