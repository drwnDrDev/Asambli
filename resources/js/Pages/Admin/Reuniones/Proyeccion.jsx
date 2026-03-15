import { useState, useEffect } from 'react'
import { router } from '@inertiajs/react'
import ProyeccionLayout from '@/Layouts/ProyeccionLayout'
import echo from '@/echo'

const BAR_COLORS = [
    'bg-blue-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-yellow-500',
    'bg-red-500',
    'bg-pink-500',
    'bg-indigo-500',
    'bg-orange-500',
]

const timeAgo = (ts) => {
    const secs = Math.floor((Date.now() - ts) / 1000)
    if (secs < 60) return `${secs}s`
    return `${Math.floor(secs / 60)}m`
}

export default function Proyeccion({ reunion, votacion: initialVotacion, resultados: initialResultados }) {
    const [votacionActiva, setVotacionActiva] = useState(initialVotacion)
    const [resultados, setResultados] = useState(initialResultados ?? [])
    const [ticker, setTicker] = useState([])
    const [tickerVisible, setTickerVisible] = useState(true)

    // Force re-render every second so timeAgo updates
    const [, setTick] = useState(0)
    useEffect(() => {
        const interval = setInterval(() => setTick(t => t + 1), 1000)
        return () => clearInterval(interval)
    }, [])

    // Echo subscriptions
    useEffect(() => {
        const publicChannel = echo.channel(`reunion.${reunion.id}`)
        publicChannel.listen('EstadoVotacionCambiado', (e) => {
            if (e.estado === 'abierta') {
                router.reload({ only: ['votacion', 'resultados'] })
            }
            if (e.estado === 'cerrada') {
                setVotacionActiva(null)
                setResultados([])
                setTicker([])
            }
        })

        const privateChannel = echo.private(`reunion.${reunion.id}`)
        privateChannel.listen('ResultadosVotacionActualizados', (e) => {
            setResultados(e.resultados ?? [])
            if (e.ultimo_voto_unidad) {
                setTicker(prev => [{ unidad: e.ultimo_voto_unidad, ts: Date.now() }, ...prev].slice(0, 15))
            }
        })

        return () => {
            echo.leave(`reunion.${reunion.id}`)
            echo.leave(`private-reunion.${reunion.id}`)
        }
    }, [reunion.id])

    // Sync state when Inertia reloads props
    useEffect(() => {
        setVotacionActiva(initialVotacion)
        setResultados(initialResultados ?? [])
    }, [initialVotacion, initialResultados])

    const maxPeso = resultados.length > 0
        ? Math.max(...resultados.map(r => parseFloat(r.peso_total) || 0))
        : 0

    const totalVotos = resultados.reduce((sum, r) => sum + (r.count || 0), 0)

    return (
        <ProyeccionLayout onTickerToggle={() => setTickerVisible(v => !v)} tickerVisible={tickerVisible}>
            {/* Reunion title */}
            <h1 className="text-4xl font-bold text-center text-white mb-2">
                {reunion.titulo}
            </h1>

            {!votacionActiva ? (
                /* ── No active votacion ────────────────────────────────── */
                <div className="flex flex-col items-center justify-center min-h-[60vh] gap-8">
                    <div className="text-center">
                        <p className="text-2xl text-gray-300 mb-2">Quórum</p>
                        <p className="text-6xl font-bold text-white">{reunion.quorum_requerido}%</p>
                        <p className="text-lg text-gray-500 mt-1">requerido</p>
                    </div>

                    <div className="flex items-center gap-4 text-gray-400 text-2xl">
                        <span className="w-4 h-4 bg-blue-400 rounded-full animate-pulse inline-block" />
                        <span>En espera de votación...</span>
                        <span className="w-4 h-4 bg-blue-400 rounded-full animate-pulse inline-block" />
                    </div>
                </div>
            ) : (
                /* ── Active votacion ───────────────────────────────────── */
                <div className="mt-6">
                    {/* Question */}
                    <p className="text-3xl font-semibold text-white text-center mb-8 leading-tight">
                        {votacionActiva.pregunta}
                    </p>

                    <hr className="border-gray-700 mb-8" />

                    <div className="flex gap-8">
                        {/* Left: Bars */}
                        <div className="flex-1 space-y-6">
                            {resultados.map((r, i) => {
                                const barWidth = maxPeso > 0
                                    ? Math.max((parseFloat(r.peso_total) / maxPeso) * 100, r.count > 0 ? 2 : 0)
                                    : 0
                                const pctVotos = totalVotos > 0
                                    ? ((r.count / totalVotos) * 100).toFixed(0)
                                    : 0
                                const colorClass = BAR_COLORS[i % BAR_COLORS.length]

                                return (
                                    <div key={r.opcion_id ?? i}>
                                        <div className="flex justify-between items-baseline mb-2">
                                            <span className="text-xl font-semibold text-white">{r.texto}</span>
                                            <span className="text-lg text-gray-300">
                                                {pctVotos}% &nbsp;·&nbsp; {r.count}v &nbsp;·&nbsp; {parseFloat(r.peso_total || 0).toFixed(1)}% coef
                                            </span>
                                        </div>
                                        <div className="w-full bg-gray-800 rounded-full h-8">
                                            <div
                                                className={`${colorClass} h-8 rounded-full transition-all duration-700`}
                                                style={{ width: `${barWidth}%` }}
                                            />
                                        </div>
                                    </div>
                                )
                            })}

                            {resultados.length === 0 && (
                                <p className="text-xl text-gray-500 text-center py-8">Esperando votos...</p>
                            )}

                            <p className="text-lg text-gray-400 pt-2 text-center">
                                {totalVotos} {totalVotos === 1 ? 'persona ha votado' : 'personas han votado'}
                            </p>
                        </div>

                        {/* Right: Ticker (toggleable) */}
                        {tickerVisible && (
                            <div className="w-56 border-l border-gray-700 pl-6 flex-shrink-0">
                                <p className="text-xs text-gray-500 uppercase tracking-widest mb-3">Votos recientes</p>
                                <div className="space-y-2 max-h-[50vh] overflow-y-auto">
                                    {ticker.length === 0 && (
                                        <p className="text-sm text-gray-600 italic">Sin votos aún</p>
                                    )}
                                    {ticker.map((t, i) => (
                                        <div key={i} className="flex items-center gap-2 text-sm">
                                            <span className="w-2 h-2 bg-green-400 rounded-full flex-shrink-0" />
                                            <span className="font-medium text-white">Apto {t.unidad}</span>
                                            <span className="text-gray-500 ml-auto text-xs">hace {timeAgo(t.ts)}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </ProyeccionLayout>
    )
}
