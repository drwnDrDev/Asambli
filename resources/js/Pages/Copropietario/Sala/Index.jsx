import { useEffect } from 'react'
import SalaLayout from '@/Layouts/SalaLayout'
import { Link, router } from '@inertiajs/react'
import echo from '@/echo'

const ESTADO_COLOR = {
    en_curso:   'text-green-400',
    ante_sala:  'text-blue-400',
    convocada:  'text-blue-400',
    borrador:   'text-slate-400',
    finalizada: 'text-slate-500',
}

const ESTADO_LABEL = {
    en_curso:  '● En curso',
    ante_sala: '◉ Ante sala',
}

export default function Index({ reuniones = [] }) {
    useEffect(() => {
        if (reuniones.length === 0) return
        reuniones.forEach(r => {
            echo.channel(`reunion.${r.id}`).listen('EstadoReunionCambiado', () => {
                router.reload({ preserveUrl: true })
            })
        })
        return () => {
            reuniones.forEach(r => echo.leave(`reunion.${r.id}`))
        }
    }, [])

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
                        <div key={r.id} className="bg-slate-800 rounded-xl p-4">
                            <div className="flex justify-between items-start mb-3">
                                <Link href={`/sala/${r.id}`} className="flex-1">
                                    <p className="font-semibold hover:text-amber-400 transition">{r.titulo}</p>
                                    <p className="text-xs text-slate-400 mt-1 capitalize">{r.tipo}</p>
                                </Link>
                                <span className={`text-xs font-medium capitalize ${ESTADO_COLOR[r.estado] ?? 'text-slate-400'}`}>
                                    {ESTADO_LABEL[r.estado] ?? r.estado}
                                </span>
                            </div>
                            {['ante_sala', 'en_curso'].includes(r.estado) && (
                                <Link
                                    href={`/sala/${r.id}/poderes/crear`}
                                    className="text-xs text-slate-400 hover:text-amber-400 transition"
                                >
                                    + Registrar poder / delegar voto
                                </Link>
                            )}
                        </div>
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
