import { useState, useEffect } from 'react'
import { router } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'
import echo from '@/echo'

export default function SalaShow({ reunion, quorum: initialQuorum, poderes = [], yaVotoPor = [], votacionAbierta = null }) {
    const [quorum, setQuorum] = useState(initialQuorum)
    const [votacionActiva, setVotacionActiva] = useState(
        votacionAbierta ? {
            votacion_id: votacionAbierta.id,
            pregunta: votacionAbierta.pregunta,
            estado: votacionAbierta.estado,
            opciones: votacionAbierta.opciones ?? [],
        } : null
    )
    const [votando, setVotando] = useState(false)
    const [votosEmitidos, setVotosEmitidos] = useState(yaVotoPor)
    const [aviso, setAviso] = useState(null)

    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel.listen('QuorumActualizado', (e) => setQuorum(e.quorumData))
        channel.listen('EstadoVotacionCambiado', (e) => {
            if (e.estado === 'abierta') {
                setVotacionActiva(e)
            } else {
                setVotacionActiva(null)
            }
        })
        channel.listen('AvisoEnviado', (e) => {
            setAviso({ mensaje: e.mensaje, ts: e.enviado_at })
            setTimeout(() => setAviso(null), 10000)
        })

        // Unirse al canal de presencia para aparecer como conectado en la vista del admin
        echo.join(`presence-reunion.${reunion.id}`)

        return () => {
            echo.leave(`reunion.${reunion.id}`)
            echo.leave(`presence-reunion.${reunion.id}`)
        }
    }, [reunion.id])

    const emitirVoto = (opcionId, enNombreDeId = null) => {
        if (votando) return
        setVotando(true)

        router.post('/votos', {
            votacion_id: votacionActiva.votacion_id,
            opcion_id: opcionId,
            en_nombre_de: enNombreDeId,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setVotosEmitidos(prev => [...prev, enNombreDeId ?? 'propio'])
            },
            onFinish: () => setVotando(false),
        })
    }

    return (
        <>
        {aviso && (
            <div className="fixed top-4 left-1/2 -translate-x-1/2 bg-yellow-400 text-yellow-900 font-semibold rounded-xl px-6 py-4 shadow-2xl z-50 max-w-sm w-full mx-4 flex items-start gap-3">
                <span className="text-lg">📢</span>
                <span className="flex-1 text-sm">{aviso.mensaje}</span>
                <button onClick={() => setAviso(null)} className="text-yellow-700 hover:text-yellow-900 text-lg font-bold leading-none">✕</button>
            </div>
        )}
        <SalaLayout>
            <div className="mb-4">
                <p className="text-slate-400 text-xs uppercase tracking-wide">{reunion.tipo}</p>
                <h1 className="text-lg font-bold">{reunion.titulo}</h1>
            </div>

            {/* Quórum */}
            <div className={`rounded-xl p-4 mb-6 text-center ${quorum?.tiene_quorum ? 'bg-green-900/40 border border-green-700' : 'bg-red-900/40 border border-red-700'}`}>
                <p className="text-xs text-slate-400 mb-1">Quórum actual</p>
                <p className="text-3xl font-bold">{quorum?.porcentaje_presente ?? 0}%</p>
                <p className={`text-sm mt-1 ${quorum?.tiene_quorum ? 'text-green-400' : 'text-red-400'}`}>
                    {quorum?.tiene_quorum ? '✓ Quórum alcanzado' : '✗ Sin quórum suficiente'}
                </p>
                <p className="text-xs text-slate-500 mt-1">Requerido: {quorum?.quorum_requerido}%</p>
            </div>

            {/* Votación activa */}
            {votacionActiva ? (
                <div className="bg-slate-800 rounded-xl p-5">
                    <p className="text-xs text-yellow-400 uppercase tracking-wide mb-1">Votación abierta</p>
                    <h2 className="font-bold text-lg mb-4">{votacionActiva.pregunta ?? votacionActiva.titulo}</h2>

                    {/* Voto propio */}
                    {!votosEmitidos.includes('propio') && (
                        <div className="mb-5">
                            <p className="text-xs text-slate-500 uppercase mb-2">Tu voto</p>
                            <div className="space-y-2">
                                {votacionActiva.opciones?.map(opcion => (
                                    <button
                                        key={opcion.id}
                                        onClick={() => emitirVoto(opcion.id)}
                                        disabled={votando}
                                        className="w-full py-4 text-base font-semibold rounded-xl bg-blue-600 hover:bg-blue-500 active:scale-95 transition disabled:opacity-50"
                                    >
                                        {opcion.texto}
                                    </button>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Votos como apoderado */}
                    {poderes.map(poder => (
                        !votosEmitidos.includes(poder.poderdante_id) && (
                            <div key={poder.id} className="border-t border-slate-700 pt-4 mb-4">
                                <p className="text-xs text-yellow-400 uppercase mb-2">
                                    En nombre de: {poder.poderdante?.user?.name}
                                </p>
                                <div className="space-y-2">
                                    {votacionActiva.opciones?.map(opcion => (
                                        <button
                                            key={opcion.id}
                                            onClick={() => emitirVoto(opcion.id, poder.poderdante_id)}
                                            disabled={votando}
                                            className="w-full py-3 text-sm font-medium rounded-xl bg-yellow-700 hover:bg-yellow-600 active:scale-95 transition disabled:opacity-50"
                                        >
                                            {opcion.texto}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        )
                    ))}

                    {votosEmitidos.length > 0 && (
                        <p className="text-center text-green-400 text-sm mt-3">
                            ✓ {votosEmitidos.length} voto(s) registrado(s)
                        </p>
                    )}
                </div>
            ) : (
                <div className="bg-slate-800 rounded-xl p-10 text-center">
                    <div className="text-5xl mb-4">⏳</div>
                    <p className="text-slate-400 text-sm">
                        Esperando que el administrador abra una votación...
                    </p>
                </div>
            )}
        </SalaLayout>
        </>
    )
}
