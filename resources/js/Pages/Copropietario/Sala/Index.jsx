import SalaLayout from '@/Layouts/SalaLayout'
import { Link } from '@inertiajs/react'

const ESTADO_COLOR = {
    en_curso:   'text-green-400',
    convocada:  'text-blue-400',
    borrador:   'text-slate-400',
    finalizada: 'text-slate-500',
}

export default function Index({ reuniones = [] }) {
    return (
        <SalaLayout>
            <h1 className="text-xl font-bold mb-6">Mis Reuniones</h1>

            {reuniones.length === 0 ? (
                <div className="bg-slate-800 rounded-xl p-8 text-center">
                    <div className="text-4xl mb-3">📋</div>
                    <p className="text-slate-400">No tienes reuniones activas.</p>
                </div>
            ) : (
                <div className="space-y-3">
                    {reuniones.map(r => (
                        <Link
                            key={r.id}
                            href={`/sala/${r.id}`}
                            className="block bg-slate-800 rounded-xl p-4 hover:bg-slate-700 transition"
                        >
                            <div className="flex justify-between items-start">
                                <div>
                                    <p className="font-semibold">{r.titulo}</p>
                                    <p className="text-xs text-slate-400 mt-1 capitalize">{r.tipo}</p>
                                </div>
                                <span className={`text-xs font-medium capitalize ${ESTADO_COLOR[r.estado]}`}>
                                    {r.estado === 'en_curso' ? '● En curso' : r.estado}
                                </span>
                            </div>
                        </Link>
                    ))}
                </div>
            )}

            <div className="mt-8 text-center">
                <Link href="/historial" className="text-sm text-slate-400 hover:text-slate-300">
                    Ver historial →
                </Link>
            </div>
        </SalaLayout>
    )
}
