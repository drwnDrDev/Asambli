// resources/js/Pages/Copropietario/Sala/Show.jsx
import { useState, useEffect, useRef } from 'react'
import { router, usePage } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'
import echo from '@/echo'

// ─── helpers ─────────────────────────────────────────────────────────────────

const TERMINAL_STATES = ['finalizada', 'cancelada', 'reprogramada']

function calcPct(pesoTotal, allResultados) {
    const suma = allResultados.reduce((acc, r) => acc + r.peso_total, 0)
    if (!suma) return 0
    return Math.round((pesoTotal / suma) * 100 * 10) / 10
}

function formatTime(isoString) {
    if (!isoString) return ''
    return new Date(isoString).toLocaleTimeString('es-CO', { hour: '2-digit', minute: '2-digit' })
}

// ─── sub-components ───────────────────────────────────────────────────────────

function ConnectionDot({ status }) {
    const colors = {
        connected:     'bg-emerald-400',
        reconnecting:  'bg-orange-400 animate-pulse',
        disconnected:  'bg-red-500',
    }
    const labels = {
        connected:    'Conectado',
        reconnecting: 'Reconectando…',
        disconnected: 'Sin conexión',
    }
    return (
        <span className="flex items-center gap-1.5 text-xs">
            <span className={`w-2 h-2 rounded-full ${colors[status]}`} />
            <span style={{ color: 'var(--sala-text-muted)' }}>{labels[status]}</span>
        </span>
    )
}

function EstadoBadge({ estado }) {
    const map = {
        en_curso:      { label: 'EN CURSO',      cls: 'text-emerald-400 border-emerald-800 bg-emerald-950/60' },
        ante_sala:     { label: 'ANTE SALA',      cls: 'text-blue-400 border-blue-800 bg-blue-950/60' },
        suspendida:    { label: 'SUSPENDIDA',     cls: 'text-orange-400 border-orange-800 bg-orange-950/60' },
        finalizada:    { label: 'FINALIZADA',     cls: 'text-red-400 border-red-900 bg-red-950/40' },
        cancelada:     { label: 'CANCELADA',      cls: 'text-red-400 border-red-900 bg-red-950/40' },
        reprogramada:  { label: 'REPROGRAMADA',   cls: 'text-red-400 border-red-900 bg-red-950/40' },
    }
    const { label, cls } = map[estado] ?? { label: estado?.toUpperCase(), cls: 'text-slate-400 border-slate-700' }
    return (
        <span className={`text-[10px] font-bold tracking-widest px-2 py-0.5 rounded border ${cls}`}>
            {label}
        </span>
    )
}

function QuorumPill({ quorum }) {
    const pct = quorum?.porcentaje_presente ?? 0
    const ok = quorum?.tiene_quorum
    return (
        <span className={`text-xs font-semibold px-2.5 py-1 rounded-full ${ok ? 'bg-emerald-900/50 text-emerald-400' : 'bg-slate-800 text-slate-400'}`}>
            {pct}% Q
        </span>
    )
}

function StatusBar({ connStatus, estadoReunion, quorum }) {
    return (
        <div
            className="sticky top-0 z-30 flex items-center justify-between px-4 py-2.5 border-b"
            style={{ background: '#0c111d', borderColor: 'var(--sala-border)' }}
        >
            <ConnectionDot status={connStatus} />
            <EstadoBadge estado={estadoReunion} />
            <QuorumPill quorum={quorum} />
        </div>
    )
}

function ConfirmModal({ opcion, onConfirm, onCancel, loading }) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" style={{ background: 'rgba(0,0,0,0.75)' }}>
            <div
                className="w-full max-w-sm rounded-2xl p-6 shadow-2xl"
                style={{ background: 'var(--sala-surface-raised)', border: '1px solid var(--sala-border)' }}
            >
                <h3 className="text-base font-semibold mb-4" style={{ color: 'var(--sala-text)' }}>
                    Confirmar voto
                </h3>
                <p className="text-sm mb-3" style={{ color: 'var(--sala-text-muted)' }}>Vas a votar por:</p>
                <div
                    className="rounded-xl px-4 py-3 mb-4 text-sm font-semibold flex items-center gap-2"
                    style={{
                        border: '1.5px solid var(--sala-amber-border)',
                        background: 'var(--sala-amber-glow)',
                        color: 'var(--sala-amber)',
                    }}
                >
                    <span>✦</span>
                    <span>{opcion.texto}</span>
                </div>
                <p className="text-xs mb-6" style={{ color: 'var(--sala-text-muted)' }}>
                    Esta acción no se puede deshacer.
                </p>
                <div className="flex gap-3">
                    <button
                        onClick={onCancel}
                        disabled={loading}
                        className="flex-1 py-2.5 rounded-xl text-sm font-medium border transition disabled:opacity-50"
                        style={{ borderColor: 'var(--sala-border)', color: 'var(--sala-text-muted)' }}
                    >
                        Cancelar
                    </button>
                    <button
                        onClick={onConfirm}
                        disabled={loading}
                        className="flex-1 py-2.5 rounded-xl text-sm font-semibold transition disabled:opacity-50 active:scale-95"
                        style={{ background: 'var(--sala-amber)', color: '#0a0f1e' }}
                    >
                        {loading ? 'Enviando…' : 'Confirmar →'}
                    </button>
                </div>
            </div>
        </div>
    )
}

function ResultBar({ opcion, resultados, esVotada }) {
    const pct = calcPct(opcion.peso_total, resultados)
    return (
        <div className="mb-3">
            <div className="flex justify-between items-center mb-1">
                <span className="text-sm font-medium" style={{ color: esVotada ? 'var(--sala-green)' : 'var(--sala-text)' }}>
                    {esVotada && '✓ '}{opcion.texto}
                </span>
                <span className="text-xs tabular-nums" style={{ color: 'var(--sala-text-muted)' }}>{pct}%</span>
            </div>
            <div className="h-2 rounded-full overflow-hidden" style={{ background: 'var(--sala-border)' }}>
                <div
                    className="h-full rounded-full transition-all duration-700"
                    style={{
                        width: `${pct}%`,
                        background: esVotada ? 'var(--sala-green)' : 'var(--sala-amber)',
                    }}
                />
            </div>
        </div>
    )
}

function VotacionCard({ votacionActiva, resultados, yaVotoPor, poderes, onVotar, loading, esDelegadoExterno }) {
    const [pendingOpcion, setPendingOpcion] = useState(null)
    const yaVotoPropio = yaVotoPor.includes('propio')

    if (!votacionActiva) {
        return (
            <div
                className="rounded-2xl p-10 text-center mb-6"
                style={{ background: 'var(--sala-surface)', border: '1px solid var(--sala-border)' }}
            >
                <div className="text-4xl mb-4">⏳</div>
                <p className="text-sm" style={{ color: 'var(--sala-text-muted)' }}>
                    El administrador abrirá la siguiente votación.
                </p>
            </div>
        )
    }

    return (
        <>
            {pendingOpcion && (
                <ConfirmModal
                    opcion={pendingOpcion}
                    loading={loading}
                    onConfirm={() => {
                        onVotar(pendingOpcion.id, pendingOpcion._enNombreDe ?? null)
                        setPendingOpcion(null)
                    }}
                    onCancel={() => setPendingOpcion(null)}
                />
            )}

            <div
                className="rounded-2xl p-5 mb-6"
                style={{
                    background: 'var(--sala-surface)',
                    border: '1.5px solid var(--sala-amber-border)',
                    boxShadow: '0 0 24px var(--sala-amber-glow)',
                }}
            >
                <p className="text-[10px] font-bold tracking-widest mb-3" style={{ color: 'var(--sala-amber)' }}>
                    VOTACIÓN ABIERTA
                </p>

                <h2
                    className="text-xl font-semibold leading-snug mb-5"
                    style={{ fontFamily: 'var(--sala-font-display)', color: 'var(--sala-text)' }}
                >
                    {votacionActiva.pregunta}
                </h2>

                {!yaVotoPropio && !esDelegadoExterno && (
                    <div className="mb-5">
                        <p className="text-[10px] uppercase tracking-widest mb-2" style={{ color: 'var(--sala-text-muted)' }}>Tu voto</p>
                        <div className="space-y-2">
                            {votacionActiva.opciones.map(opcion => (
                                <button
                                    key={opcion.id}
                                    onClick={() => setPendingOpcion(opcion)}
                                    disabled={loading}
                                    className="w-full py-3.5 text-sm font-semibold rounded-xl transition active:scale-95 disabled:opacity-50"
                                    style={{
                                        background: 'var(--sala-surface-raised)',
                                        border: '1px solid var(--sala-border)',
                                        color: 'var(--sala-text)',
                                    }}
                                    onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--sala-amber)'}
                                    onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--sala-border)'}
                                >
                                    {opcion.texto}
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                {yaVotoPropio && resultados && !esDelegadoExterno && (
                    <div className="mb-4">
                        {resultados.map(r => (
                            <ResultBar
                                key={r.opcion_id}
                                opcion={r}
                                resultados={resultados}
                                esVotada={false}
                            />
                        ))}
                        <p className="text-xs mt-3 text-center" style={{ color: 'var(--sala-green)' }}>
                            ✓ Tu voto fue registrado
                        </p>
                    </div>
                )}

                {poderes.map(poder => {
                    const yaVotoPoder = yaVotoPor.includes(poder.poderdante_id)
                    return (
                        <div key={poder.id} className="border-t pt-4 mt-4" style={{ borderColor: 'var(--sala-border)' }}>
                            <p className="text-[10px] uppercase tracking-widest mb-2" style={{ color: 'var(--sala-amber)' }}>
                                En nombre de: {poder.poderdante?.user?.name}
                            </p>
                            {!yaVotoPoder ? (
                                <div className="space-y-2">
                                    {votacionActiva.opciones.map(opcion => (
                                        <button
                                            key={opcion.id}
                                            onClick={() => setPendingOpcion({ ...opcion, _enNombreDe: poder.poderdante_id })}
                                            disabled={loading}
                                            className="w-full py-3 text-sm font-medium rounded-xl transition active:scale-95 disabled:opacity-50"
                                            style={{
                                                background: 'var(--sala-surface-raised)',
                                                border: '1px solid var(--sala-amber-border)',
                                                color: 'var(--sala-text)',
                                            }}
                                        >
                                            {opcion.texto}
                                        </button>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-xs" style={{ color: 'var(--sala-green)' }}>✓ Voto registrado</p>
                            )}
                        </div>
                    )
                })}
            </div>
        </>
    )
}

function FeedItem({ item }) {
    const iconMap = {
        en_curso:       { icon: '▶', color: 'var(--sala-green)',  bg: 'var(--sala-green-bg)' },
        ante_sala:      { icon: '◉', color: 'var(--sala-blue)',   bg: 'var(--sala-blue-bg)' },
        suspendida:     { icon: '⏸', color: 'var(--sala-orange)', bg: 'var(--sala-orange-bg)' },
        finalizada:     { icon: '⏹', color: 'var(--sala-red)',    bg: 'var(--sala-red-bg)' },
        cancelada:      { icon: '✕', color: 'var(--sala-red)',    bg: 'var(--sala-red-bg)' },
        reprogramada:   { icon: '↺', color: 'var(--sala-red)',    bg: 'var(--sala-red-bg)' },
    }
    const estadoLabels = {
        en_curso:     'Reunión iniciada',
        ante_sala:    'Ante sala abierta',
        suspendida:   'Reunión suspendida',
        finalizada:   'Reunión finalizada',
        cancelada:    'Reunión cancelada',
        reprogramada: 'Reunión reprogramada',
    }

    if (item.tipo === 'estado_reunion') {
        const { icon, color, bg } = iconMap[item.estado] ?? { icon: '●', color: 'var(--sala-text-muted)', bg: 'transparent' }
        return (
            <div className="flex items-center gap-3 py-2.5">
                <span className="w-7 h-7 rounded-full flex items-center justify-center text-xs flex-shrink-0" style={{ background: bg, color }}>
                    {icon}
                </span>
                <span className="text-sm flex-1" style={{ color: 'var(--sala-text-muted)' }}>
                    {estadoLabels[item.estado] ?? item.estado}
                </span>
                <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                    {formatTime(item.timestamp)}
                </span>
            </div>
        )
    }

    if (item.tipo === 'votacion_abierta') {
        return (
            <div className="flex items-start gap-3 py-2.5">
                <span className="text-base flex-shrink-0">🗳</span>
                <span className="text-sm flex-1" style={{ color: 'var(--sala-amber)' }}>Votación abierta: {item.pregunta}</span>
                <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                    {formatTime(item.timestamp)}
                </span>
            </div>
        )
    }

    if (item.tipo === 'votacion_cerrada') {
        return (
            <div
                className="rounded-xl p-3 my-1.5"
                style={{ background: 'var(--sala-surface)', border: '1px solid var(--sala-border)' }}
            >
                <div className="flex items-start gap-2.5">
                    <span className="text-base flex-shrink-0 mt-0.5">🗳</span>
                    <div className="flex-1 min-w-0">
                        <p className="text-xs font-medium truncate" style={{ color: 'var(--sala-text)' }}>
                            {item.pregunta}
                        </p>
                        <p className="text-xs mt-0.5" style={{ color: 'var(--sala-green)' }}>
                            Ganó: {item.ganadora} ({item.ganadora_pct}%)
                        </p>
                    </div>
                    <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                        {formatTime(item.timestamp)}
                    </span>
                </div>
            </div>
        )
    }

    if (item.tipo === 'aviso') {
        return (
            <div className="flex items-start gap-3 py-2.5">
                <span className="text-base flex-shrink-0">📢</span>
                <span className="text-sm flex-1" style={{ color: 'var(--sala-text-muted)' }}>{item.mensaje}</span>
                <span className="text-xs tabular-nums flex-shrink-0" style={{ color: 'var(--sala-text-faint)' }}>
                    {formatTime(item.timestamp)}
                </span>
            </div>
        )
    }

    return null
}

function TerminalBanner({ estado, countdown }) {
    const labels = {
        finalizada:   'La reunión ha finalizado.',
        cancelada:    'La reunión fue cancelada.',
        reprogramada: 'La reunión fue reprogramada.',
    }
    return (
        <div
            className="fixed inset-x-0 top-0 z-40 px-4 py-3 text-center text-sm font-medium"
            style={{ background: 'var(--sala-red)', color: '#fff' }}
        >
            {labels[estado] ?? 'La reunión terminó.'} Redirigiendo en {countdown}s…
        </div>
    )
}

// ─── main component ────────────────────────────────────────────────────────────

export default function SalaShow({
    reunion,
    quorum: initialQuorum,
    poderes = [],
    yaVotoPor: initialYaVotoPor = [],
    votacionAbierta = null,
    resultadosActuales: initialResultados = null,
    feedInicial = [],
    estadoReunion: initialEstadoReunion,
    esDelegadoExterno = false,
}) {
    const { errors } = usePage().props

    const [connStatus, setConnStatus]       = useState('connected')
    const [quorum, setQuorum]               = useState(initialQuorum)
    const [estadoReunion, setEstadoReunion] = useState(initialEstadoReunion)
    const [votacionActiva, setVotacionActiva] = useState(
        votacionAbierta ? {
            votacion_id: votacionAbierta.id,
            pregunta:    votacionAbierta.pregunta,
            estado:      votacionAbierta.estado,
            opciones:    votacionAbierta.opciones ?? [],
        } : null
    )
    const [votando, setVotando]             = useState(false)
    const [yaVotoPor, setYaVotoPor]         = useState(initialYaVotoPor)
    const [resultados, setResultados]       = useState(initialResultados)
    const [aviso, setAviso]                 = useState(null)
    const [feed, setFeed]                   = useState([...feedInicial].reverse())
    const [terminalCountdown, setTerminalCountdown] = useState(null)
    const countdownRef = useRef(null)
    const votacionActivaRef = useRef(votacionActiva)
    const resultadosRef = useRef(resultados)
    useEffect(() => { votacionActivaRef.current = votacionActiva }, [votacionActiva])
    useEffect(() => { resultadosRef.current = resultados }, [resultados])

    // Sync from server props after Inertia redirect (e.g. after voting)
    useEffect(() => { setYaVotoPor(initialYaVotoPor) }, [initialYaVotoPor])
    useEffect(() => { setResultados(initialResultados) }, [initialResultados])

    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel
            .listen('QuorumActualizado', (e) => setQuorum(e.quorumData))
            .listen('EstadoVotacionCambiado', (e) => {
                const now = new Date().toISOString()
                if (e.estado === 'abierta') {
                    setVotacionActiva(e)
                    setResultados(null)
                    setFeed(prev => [{ tipo: 'votacion_abierta', pregunta: e.pregunta, timestamp: now }, ...prev])
                } else {
                    // Calculate winner from last known resultados before clearing
                    const cur = resultadosRef.current
                    const feedItem = { tipo: 'votacion_cerrada', pregunta: votacionActivaRef.current?.pregunta ?? e.pregunta, timestamp: now }
                    if (cur && cur.length > 0) {
                        const total = cur.reduce((s, r) => s + r.peso_total, 0)
                        const ganadora = cur.reduce((max, r) => r.peso_total > max.peso_total ? r : max, cur[0])
                        feedItem.ganadora = ganadora.texto
                        feedItem.ganadora_pct = total > 0 ? Math.round((ganadora.peso_total / total) * 100 * 10) / 10 : 0
                    }
                    setVotacionActiva(null)
                    setResultados(null)
                    setFeed(prev => [feedItem, ...prev])
                }
            })
            .listen('VotacionModificada', (e) => {
                if (e.accion === 'updated' && votacionActivaRef.current?.votacion_id === e.votacion_id) {
                    setVotacionActiva(prev => ({
                        ...prev,
                        pregunta: e.pregunta,
                        opciones: e.opciones ?? prev.opciones,
                    }))
                }
            })
            .listen('AvisoEnviado', (e) => {
                setAviso({ mensaje: e.mensaje, ts: e.enviado_at })
                setFeed(prev => [{ tipo: 'aviso', mensaje: e.mensaje, timestamp: e.enviado_at }, ...prev])
                setTimeout(() => setAviso(null), 10000)
            })
            .listen('ResultadosPublicosVotacion', (e) => {
                if (votacionActivaRef.current && e.votacion_id === votacionActivaRef.current.votacion_id) {
                    setResultados(e.resultados)
                }
            })
            .listen('EstadoReunionCambiado', (e) => {
                setEstadoReunion(e.estado)
                setFeed(prev => [{ tipo: 'estado_reunion', estado: e.estado, timestamp: e.timestamp }, ...prev])
                if (TERMINAL_STATES.includes(e.estado)) {
                    startTerminalCountdown()
                }
            })

        echo.connector.pusher?.connection?.bind('connected',      () => setConnStatus('connected'))
        echo.connector.pusher?.connection?.bind('connecting',     () => setConnStatus('reconnecting'))
        echo.connector.pusher?.connection?.bind('disconnected',   () => setConnStatus('disconnected'))
        echo.connector.pusher?.connection?.bind('unavailable',    () => setConnStatus('disconnected'))

        echo.join(`presence-reunion.${reunion.id}`)

        return () => {
            echo.leave(`reunion.${reunion.id}`)
            echo.leave(`presence-reunion.${reunion.id}`)
            if (countdownRef.current) clearInterval(countdownRef.current)
        }
    }, [reunion.id])

    function startTerminalCountdown() {
        setTerminalCountdown(10)
        countdownRef.current = setInterval(() => {
            setTerminalCountdown(prev => {
                if (prev <= 1) {
                    clearInterval(countdownRef.current)
                    router.visit('/historial')
                    return 0
                }
                return prev - 1
            })
        }, 1000)
    }

    const emitirVoto = (opcionId, enNombreDeId = null) => {
        if (votando) return
        setVotando(true)
        router.post('/votos', {
            votacion_id:  votacionActiva.votacion_id,
            opcion_id:    opcionId,
            en_nombre_de: enNombreDeId,
        }, {
            preserveScroll: true,
            // yaVotoPor and resultados are synced from server props via useEffect
            onFinish: () => setVotando(false),
        })
    }

    const isTerminal = TERMINAL_STATES.includes(estadoReunion)

    return (
        <div style={{ minHeight: '100dvh', background: 'var(--sala-bg)', fontFamily: 'var(--sala-font-body)', color: 'var(--sala-text)' }}>
            {aviso && (
                <div className="fixed top-16 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-2rem)] max-w-sm rounded-xl px-4 py-3 shadow-2xl flex items-start gap-3"
                    style={{ background: 'var(--sala-amber)', color: '#0a0f1e' }}
                >
                    <span className="text-lg">📢</span>
                    <span className="flex-1 text-sm font-medium">{aviso.mensaje}</span>
                    <button onClick={() => setAviso(null)} className="font-bold text-lg leading-none opacity-60 hover:opacity-100">✕</button>
                </div>
            )}

            {isTerminal && terminalCountdown !== null && (
                <TerminalBanner estado={estadoReunion} countdown={terminalCountdown} />
            )}

            <StatusBar connStatus={connStatus} estadoReunion={estadoReunion} quorum={quorum} />

            <div className="px-4 py-5 max-w-lg mx-auto">
                <div className="mb-5">
                    <p className="text-xs uppercase tracking-wide mb-0.5" style={{ color: 'var(--sala-text-muted)' }}>
                        {reunion.tipo}
                    </p>
                    <h1 className="text-lg font-semibold" style={{ color: 'var(--sala-text)' }}>
                        {reunion.titulo}
                    </h1>
                </div>

                {errors?.voto && (
                    <div
                        className="rounded-xl px-4 py-3 mb-4 text-sm"
                        style={{ background: 'var(--sala-red-bg)', border: '1px solid var(--sala-red)', color: 'var(--sala-red)' }}
                    >
                        ⚠ {errors.voto}
                    </div>
                )}

                {esDelegadoExterno && (
                    <div
                        className="rounded-xl px-4 py-3 mb-4 text-xs font-medium"
                        style={{ background: 'rgba(251,191,36,0.12)', border: '1px solid var(--sala-amber-border)', color: 'var(--sala-amber)' }}
                    >
                        Estás participando como <strong>delegado</strong>. Vota en nombre de los copropietarios que te autorizaron.
                    </div>
                )}
                <VotacionCard
                    votacionActiva={votacionActiva}
                    resultados={resultados}
                    yaVotoPor={yaVotoPor}
                    poderes={poderes}
                    onVotar={emitirVoto}
                    loading={votando}
                    esDelegadoExterno={esDelegadoExterno}
                />

                {feed.length > 0 && (
                    <div>
                        <p className="text-[10px] uppercase tracking-widest mb-3" style={{ color: 'var(--sala-text-faint)' }}>
                            Cronología
                        </p>
                        <div className="divide-y" style={{ borderColor: 'var(--sala-border)' }}>
                            {feed.map((item, i) => (
                                <FeedItem key={i} item={item} />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </div>
    )
}
