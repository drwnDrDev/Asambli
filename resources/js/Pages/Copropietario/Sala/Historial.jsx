import SalaLayout from '@/Layouts/SalaLayout'
import { Link } from '@inertiajs/react'

export default function Historial({ reuniones = [] }) {
    return (
        <SalaLayout>
            <h1 className="text-xl font-bold mb-6">Historial de Reuniones</h1>

            {reuniones.length === 0 ? (
                <div className="bg-slate-800 rounded-xl p-8 text-center">
                    <div className="text-4xl mb-3">📂</div>
                    <p className="text-slate-400">No hay reuniones finalizadas.</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {reuniones.map(r => (
                        <div key={r.id} className="bg-slate-800 rounded-xl p-4">
                            <p className="font-semibold">{r.titulo}</p>
                            <p className="text-xs text-slate-400 mt-1">
                                {r.fecha_inicio ?? r.fecha_programada ?? 'Sin fecha'} · {r.tipo}
                            </p>
                        </div>
                    ))}
                </div>
            )}

            <div className="mt-6">
                <Link href="/sala" className="text-sm text-slate-400 hover:text-slate-300">← Volver</Link>
            </div>
        </SalaLayout>
    )
}
