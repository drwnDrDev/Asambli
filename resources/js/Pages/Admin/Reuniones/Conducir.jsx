import { useState, useEffect, useRef } from 'react'
import { router, usePage, useForm } from '@inertiajs/react'
import AdminLayout from '@/Layouts/AdminLayout'
import echo from '@/echo'

// ─── ModalConectados ────────────────────────────────────────────────
function ModalConectados({ conectados, onClose }) {
    const [filtro, setFiltro] = useState('')

    const sorted = [...conectados].sort((a, b) => {
        if (a.unidad == null && b.unidad == null) return 0
        if (a.unidad == null) return 1
        if (b.unidad == null) return -1
        return Number(a.unidad) - Number(b.unidad)
    })

    const filtered = filtro.trim()
        ? sorted.filter(c => String(c.unidad ?? '').includes(filtro.trim()))
        : sorted

    const sumaCoef = filtered.reduce((sum, c) => sum + (parseFloat(c.coef) || 0), 0)

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm" onClick={onClose}>
            <div className="bg-white rounded-lg shadow-xl w-full max-w-lg mx-4 max-h-[80vh] flex flex-col" onClick={e => e.stopPropagation()}>
                <div className="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                    <h3 className="font-semibold text-gray-900">Conectados ({conectados.length})</h3>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600 transition">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div className="px-5 py-3 border-b border-gray-100">
                    <input
                        type="text"
                        placeholder="Filtrar por unidad..."
                        value={filtro}
                        onChange={e => setFiltro(e.target.value)}
                        className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        autoFocus
                    />
                </div>
                <div className="flex-1 overflow-y-auto">
                    <table className="w-full text-sm">
                        <thead className="bg-gray-50 sticky top-0">
                            <tr>
                                <th className="text-left px-5 py-2 text-gray-500 font-medium">Unidad</th>
                                <th className="text-left px-5 py-2 text-gray-500 font-medium">Nombre</th>
                                <th className="text-right px-5 py-2 text-gray-500 font-medium">Coef%</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map(c => (
                                <tr key={c.id} className="border-b border-gray-50 hover:bg-gray-50">
                                    <td className="px-5 py-2 font-medium">{c.unidad ?? '—'}</td>
                                    <td className="px-5 py-2 text-gray-700">{c.nombre}</td>
                                    <td className="px-5 py-2 text-right text-gray-600">{c.coef != null ? `${parseFloat(c.coef).toFixed(2)}%` : '—'}</td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr><td colSpan={3} className="px-5 py-6 text-center text-gray-400">Sin resultados</td></tr>
                            )}
                        </tbody>
                    </table>
                </div>
                <div className="px-5 py-3 border-t border-gray-200 text-sm text-gray-600 flex justify-between">
                    <span>{filtered.length} conectado{filtered.length !== 1 ? 's' : ''}</span>
                    <span className="font-medium">Coef. total: {sumaCoef.toFixed(2)}%</span>
                </div>
            </div>
        </div>
    )
}

// ─── Conducir (Layout C) ───────────────────────────────────────────
export default function Conducir({ reunion, quorum: initialQuorum, copropietarios = [], votaciones: initialVotaciones = [] }) {
    const { flash, errors: pageErrors } = usePage().props
    const [quorum, setQuorum] = useState(initialQuorum)
    const [votaciones, setVotaciones] = useState(initialVotaciones)
    const [resultados, setResultados] = useState({})
    const [conectados, setConectados] = useState([])
    const [showConectados, setShowConectados] = useState(false)
    const [ticker, setTicker] = useState([])
    const [observacion, setObservacion] = useState('')
    const [transitionErrors, setTransitionErrors] = useState({})
    const [aviso, setAviso] = useState('')

    // Votacion creation form
    const [showCreateForm, setShowCreateForm] = useState(false)
    const { data, setData, post, processing, reset, errors } = useForm({
        pregunta: '',
        opciones: [{ texto: 'Si' }, { texto: 'No' }, { texto: 'Abstención' }],
    })

    // Edit state
    const [editingId, setEditingId] = useState(null)
    const [editData, setEditData] = useState({ pregunta: '', opciones: [] })

    // Ticker time updater
    const [, setTickerTick] = useState(0)
    useEffect(() => {
        const interval = setInterval(() => setTickerTick(t => t + 1), 5000)
        return () => clearInterval(interval)
    }, [])

    // ─── Echo subscriptions ─────────────────────────────────────
    useEffect(() => {
        // 1. Public channel
        const publicChannel = echo.channel(`reunion.${reunion.id}`)
        publicChannel.listen('.QuorumActualizado', (e) => {
            setQuorum(e.quorumData)
        })
        publicChannel.listen('.EstadoVotacionCambiado', (e) => {
            setVotaciones(prev => prev.map(v =>
                v.id === e.votacion_id ? { ...v, estado: e.estado } : v
            ))
        })
        publicChannel.listen('.VotacionModificada', (e) => {
            if (e.accion === 'created') {
                setVotaciones(prev => [...prev, { id: e.votacion_id, pregunta: e.pregunta, estado: e.estado, opciones: e.opciones }])
            } else if (e.accion === 'updated') {
                setVotaciones(prev => prev.map(v =>
                    v.id === e.votacion_id ? { ...v, pregunta: e.pregunta, opciones: e.opciones, estado: e.estado } : v
                ))
            } else if (e.accion === 'deleted') {
                setVotaciones(prev => prev.filter(v => v.id !== e.votacion_id))
            }
        })

        // 2. Private channel
        const privateChannel = echo.private(`reunion.${reunion.id}`)
        privateChannel.listen('.ResultadosVotacionActualizados', (e) => {
            setResultados(prev => ({ ...prev, [e.votacion_id]: e.resultados }))
            if (e.ultimo_voto_unidad) {
                setTicker(prev => [{ unidad: e.ultimo_voto_unidad, ts: Date.now() }, ...prev].slice(0, 20))
            }
        })

        // 3. Presence channel
        const presenceChannel = echo.join(`presence-reunion.${reunion.id}`)
        presenceChannel
            .here((members) => setConectados(members))
            .joining((member) => setConectados(prev => [...prev, member]))
            .leaving((member) => setConectados(prev => prev.filter(m => m.id !== member.id)))

        return () => {
            echo.leave(`reunion.${reunion.id}`)
            echo.leave(`private-reunion.${reunion.id}`)
            echo.leave(`presence-reunion.${reunion.id}`)
        }
    }, [reunion.id])

    // ─── Helpers ────────────────────────────────────────────────
    const estadoLabel = {
        borrador: 'Borrador',
        convocada: 'Convocada',
        ante_sala: 'Ante Sala',
        en_curso: 'En Curso',
        suspendida: 'Suspendida',
        finalizada: 'Finalizada',
        cancelada: 'Cancelada',
    }

    const estadoColor = {
        borrador: 'bg-gray-100 text-gray-600',
        convocada: 'bg-blue-100 text-blue-700',
        ante_sala: 'bg-yellow-100 text-yellow-700',
        en_curso: 'bg-green-100 text-green-700',
        suspendida: 'bg-orange-100 text-orange-700',
        finalizada: 'bg-gray-200 text-gray-600',
        cancelada: 'bg-red-100 text-red-700',
    }

    const votacionActiva = votaciones.find(v => v.estado === 'abierta')

    const timeAgo = (ts) => {
        const secs = Math.floor((Date.now() - ts) / 1000)
        if (secs < 60) return `hace ${secs}s`
        return `hace ${Math.floor(secs / 60)}m`
    }

    // ─── State transitions ──────────────────────────────────────
    const doTransition = (action) => {
        setTransitionErrors({})
        router.post(`/admin/reuniones/${reunion.id}/${action}`, { observacion }, {
            preserveScroll: true,
            onSuccess: () => setObservacion(''),
            onError: (errs) => setTransitionErrors(errs),
        })
    }

    // ─── Votaciones CRUD ────────────────────────────────────────
    const submitVotacion = (e) => {
        e.preventDefault()
        post(`/admin/reuniones/${reunion.id}/votaciones`, {
            preserveScroll: true,
            onSuccess: () => { reset(); setShowCreateForm(false) },
        })
    }

    const startEdit = (v) => {
        setEditingId(v.id)
        setEditData({ pregunta: v.pregunta, opciones: v.opciones?.map(o => ({ id: o.id, texto: o.texto })) || [] })
    }

    const submitEdit = (e) => {
        e.preventDefault()
        router.patch(`/admin/votaciones/${editingId}`, editData, {
            preserveScroll: true,
            onSuccess: () => setEditingId(null),
        })
    }

    const deleteVotacion = (id) => {
        if (window.confirm('¿Eliminar esta votación?')) {
            router.delete(`/admin/votaciones/${id}`, { preserveScroll: true })
        }
    }

    const abrirVotacion = (id) => router.post(`/admin/votaciones/${id}/abrir`, {}, { preserveScroll: true })
    const cerrarVotacion = (id) => router.post(`/admin/votaciones/${id}/cerrar`, {}, { preserveScroll: true })

    const addOpcion = () => setData('opciones', [...data.opciones, { texto: '' }])
    const removeOpcion = (i) => setData('opciones', data.opciones.filter((_, idx) => idx !== i))
    const setOpcionTexto = (i, val) => {
        const opts = [...data.opciones]
        opts[i] = { texto: val }
        setData('opciones', opts)
    }

    // Edit opciones helpers
    const addEditOpcion = () => setEditData(d => ({ ...d, opciones: [...d.opciones, { texto: '' }] }))
    const removeEditOpcion = (i) => setEditData(d => ({ ...d, opciones: d.opciones.filter((_, idx) => idx !== i) }))
    const setEditOpcionTexto = (i, val) => {
        setEditData(d => {
            const opts = [...d.opciones]
            opts[i] = { ...opts[i], texto: val }
            return { ...d, opciones: opts }
        })
    }

    // ─── Aviso ──────────────────────────────────────────────────
    const enviarAviso = (e) => {
        e.preventDefault()
        if (!aviso.trim()) return
        router.post(`/admin/reuniones/${reunion.id}/aviso`, { mensaje: aviso }, {
            preserveScroll: true,
            onSuccess: () => setAviso(''),
        })
    }

    // ─── Attendance ─────────────────────────────────────────────
    const confirmarAsistencia = (copropietarioId) =>
        router.post(`/admin/reuniones/${reunion.id}/copropietarios/${copropietarioId}/asistencia`, {}, { preserveScroll: true })

    const asistenciaConfirmada = copropietarios.filter(c => c.asistencia).length

    // ─── Votacion result helpers ────────────────────────────────
    const getResultados = (votacionId) => resultados[votacionId] || []
    const getTotalVotos = (votacionId) => {
        const res = getResultados(votacionId)
        return res.reduce((sum, r) => sum + (r.count || 0), 0)
    }

    // ─── Render ─────────────────────────────────────────────────
    return (
        <AdminLayout title={`Conducir — ${reunion.titulo}`}>
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            {/* ── KPI Cards ───────────────────────────────────── */}
            <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                {/* Estado */}
                <div className="bg-white rounded-lg shadow p-4">
                    <p className="text-xs text-gray-500 uppercase tracking-wider mb-1">Estado</p>
                    <span className={`inline-block text-sm font-semibold px-2.5 py-1 rounded-full ${estadoColor[reunion.estado] || 'bg-gray-100 text-gray-600'}`}>
                        {estadoLabel[reunion.estado] || reunion.estado}
                    </span>
                </div>

                {/* Quorum */}
                <div className={`rounded-lg shadow p-4 border ${quorum.tiene_quorum ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
                    <p className="text-xs text-gray-500 uppercase tracking-wider mb-1">Quorum</p>
                    <p className="text-xl font-bold">
                        {quorum.porcentaje_presente}%
                        <span className="ml-1 text-sm">{quorum.tiene_quorum ? '✓' : '✗'}</span>
                    </p>
                    <p className="text-xs text-gray-500">
                        Req: {quorum.quorum_requerido}% · {quorum.presente}/{quorum.total}
                    </p>
                </div>

                {/* Conectados */}
                <button
                    onClick={() => setShowConectados(true)}
                    className="bg-white rounded-lg shadow p-4 text-left hover:bg-blue-50 transition cursor-pointer"
                >
                    <p className="text-xs text-gray-500 uppercase tracking-wider mb-1">Conectados</p>
                    <p className="text-xl font-bold text-blue-600">{conectados.length}</p>
                    <p className="text-xs text-blue-500">Ver detalles &rarr;</p>
                </button>

                {/* Votaciones */}
                <div className="bg-white rounded-lg shadow p-4">
                    <p className="text-xs text-gray-500 uppercase tracking-wider mb-1">Votaciones</p>
                    <p className="text-xl font-bold">
                        {votaciones.filter(v => v.estado === 'abierta').length > 0
                            ? <span className="text-green-600">{votaciones.filter(v => v.estado === 'abierta').length} activa</span>
                            : <span className="text-gray-500">0 activas</span>
                        }
                    </p>
                    <p className="text-xs text-gray-500">{votaciones.length} total</p>
                </div>
            </div>

            {/* ── Votacion Activa ─────────────────────────────── */}
            {votacionActiva && (
                <div className="bg-white rounded-lg shadow p-5 mb-6 border-l-4 border-green-500">
                    <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2">
                            <span className="w-2.5 h-2.5 bg-green-500 rounded-full animate-pulse" />
                            <span className="text-xs font-semibold text-green-700 uppercase tracking-wider">Votación Activa</span>
                        </div>
                        <button
                            onClick={() => cerrarVotacion(votacionActiva.id)}
                            className="text-xs bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 transition"
                        >
                            Cerrar votación
                        </button>
                    </div>
                    <p className="font-medium text-gray-900 mb-4">{votacionActiva.pregunta}</p>

                    <div className="flex gap-6">
                        {/* Bars */}
                        <div className="flex-1 space-y-2">
                            {getResultados(votacionActiva.id).map((r, i) => {
                                const total = getTotalVotos(votacionActiva.id)
                                const pct = total > 0 ? ((r.count / total) * 100) : 0
                                return (
                                    <div key={i}>
                                        <div className="flex justify-between text-sm mb-0.5">
                                            <span className="font-medium text-gray-700">{r.texto}</span>
                                            <span className="text-gray-500">
                                                {pct.toFixed(0)}% · {r.count}v · {parseFloat(r.peso_total || 0).toFixed(1)}% coef
                                            </span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-3">
                                            <div
                                                className="bg-blue-500 h-3 rounded-full transition-all duration-500"
                                                style={{ width: `${Math.max(pct, 1)}%` }}
                                            />
                                        </div>
                                    </div>
                                )
                            })}
                            {getResultados(votacionActiva.id).length === 0 && (
                                <p className="text-sm text-gray-400">Esperando votos...</p>
                            )}
                            <p className="text-xs text-gray-500 mt-2">
                                {getTotalVotos(votacionActiva.id)} de {conectados.length} han votado
                            </p>
                        </div>

                        {/* Ticker */}
                        {ticker.length > 0 && (
                            <div className="w-40 border-l border-gray-200 pl-4 max-h-48 overflow-y-auto">
                                <p className="text-xs text-gray-400 uppercase tracking-wider mb-2">Votos recientes</p>
                                {ticker.map((t, i) => (
                                    <div key={i} className="flex items-center gap-1.5 text-xs text-gray-600 py-0.5">
                                        <span className="w-1.5 h-1.5 bg-green-400 rounded-full flex-shrink-0" />
                                        <span className="font-medium">Apto {t.unidad}</span>
                                        <span className="text-gray-400 ml-auto">{timeAgo(t.ts)}</span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* ── Lower two-column panel ─────────────────────── */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Left: Todas las votaciones */}
                <div className="space-y-4">
                    <div className="bg-white rounded-lg shadow p-4">
                        <div className="flex items-center justify-between mb-3">
                            <h2 className="font-semibold text-gray-900">Todas las Votaciones</h2>
                        </div>

                        {votaciones.length === 0 && (
                            <p className="text-sm text-gray-400 py-4">No hay votaciones aún.</p>
                        )}

                        <div className="space-y-2 max-h-80 overflow-y-auto">
                            {votaciones.map(v => {
                                const isEditing = editingId === v.id

                                if (isEditing) {
                                    return (
                                        <form key={v.id} onSubmit={submitEdit} className="border border-blue-200 bg-blue-50 rounded p-3 space-y-2">
                                            <input
                                                type="text"
                                                value={editData.pregunta}
                                                onChange={e => setEditData(d => ({ ...d, pregunta: e.target.value }))}
                                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                                            />
                                            {editData.opciones.map((op, i) => (
                                                <div key={i} className="flex gap-2">
                                                    <input
                                                        type="text"
                                                        value={op.texto}
                                                        onChange={e => setEditOpcionTexto(i, e.target.value)}
                                                        className="flex-1 border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400"
                                                    />
                                                    {editData.opciones.length > 2 && (
                                                        <button type="button" onClick={() => removeEditOpcion(i)}
                                                            className="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                                                    )}
                                                </div>
                                            ))}
                                            <div className="flex gap-2">
                                                <button type="button" onClick={addEditOpcion} className="text-xs text-blue-600 hover:underline">+ Opción</button>
                                                <div className="ml-auto flex gap-2">
                                                    <button type="button" onClick={() => setEditingId(null)}
                                                        className="text-xs text-gray-500 hover:text-gray-700 px-2 py-1">Cancelar</button>
                                                    <button type="submit"
                                                        className="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition">Guardar</button>
                                                </div>
                                            </div>
                                        </form>
                                    )
                                }

                                return (
                                    <div key={v.id} className="flex items-center gap-2 py-2 border-b border-gray-50">
                                        <span className={`flex-shrink-0 w-2 h-2 rounded-full ${
                                            v.estado === 'abierta' ? 'bg-green-500' :
                                            v.estado === 'cerrada' ? 'bg-gray-400' :
                                            'bg-yellow-400'
                                        }`} />
                                        <span className="text-xs font-medium uppercase text-gray-400 w-12 flex-shrink-0">
                                            {v.estado === 'abierta' ? 'ABIER' : v.estado === 'cerrada' ? 'CERR' : 'PEND'}
                                        </span>
                                        <span className="text-sm text-gray-800 flex-1 truncate">{v.pregunta}</span>
                                        <div className="flex gap-1 flex-shrink-0">
                                            {v.estado === 'pendiente' && (
                                                <>
                                                    <button onClick={() => abrirVotacion(v.id)}
                                                        className="text-xs bg-green-600 text-white px-2 py-0.5 rounded hover:bg-green-700 transition">Abrir</button>
                                                    <button onClick={() => startEdit(v)}
                                                        className="text-xs text-blue-600 hover:text-blue-800 px-1">Editar</button>
                                                    <button onClick={() => deleteVotacion(v.id)}
                                                        className="text-xs text-red-400 hover:text-red-600 px-1">✕</button>
                                                </>
                                            )}
                                            {v.estado === 'abierta' && (
                                                <button onClick={() => cerrarVotacion(v.id)}
                                                    className="text-xs bg-red-600 text-white px-2 py-0.5 rounded hover:bg-red-700 transition">Cerrar</button>
                                            )}
                                        </div>
                                    </div>
                                )
                            })}
                        </div>

                        {/* Create form toggle */}
                        {!showCreateForm ? (
                            <button
                                onClick={() => setShowCreateForm(true)}
                                className="mt-3 text-sm text-blue-600 hover:text-blue-800 font-medium"
                            >
                                + Nueva votación
                            </button>
                        ) : (
                            <form onSubmit={submitVotacion} className="mt-3 border border-gray-200 rounded p-3 space-y-2 bg-gray-50">
                                <input
                                    type="text"
                                    placeholder="Pregunta de la votación..."
                                    value={data.pregunta}
                                    onChange={e => setData('pregunta', e.target.value)}
                                    className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                />
                                {errors.pregunta && <p className="text-red-500 text-xs">{errors.pregunta}</p>}
                                <div className="space-y-1.5">
                                    {data.opciones.map((op, i) => (
                                        <div key={i} className="flex gap-2">
                                            <input
                                                type="text"
                                                value={op.texto}
                                                onChange={e => setOpcionTexto(i, e.target.value)}
                                                placeholder={`Opción ${i + 1}`}
                                                className="flex-1 border border-gray-200 rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400"
                                            />
                                            {data.opciones.length > 2 && (
                                                <button type="button" onClick={() => removeOpcion(i)}
                                                    className="text-red-400 hover:text-red-600 text-sm px-1">✕</button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                                <div className="flex gap-2 items-center">
                                    <button type="button" onClick={addOpcion} className="text-xs text-blue-600 hover:underline">+ Opción</button>
                                    <div className="ml-auto flex gap-2">
                                        <button type="button" onClick={() => { setShowCreateForm(false); reset() }}
                                            className="text-xs text-gray-500 hover:text-gray-700 px-2 py-1">Cancelar</button>
                                        <button type="submit" disabled={processing}
                                            className="text-xs bg-blue-600 text-white px-3 py-1.5 rounded hover:bg-blue-700 disabled:opacity-50 transition">
                                            Crear
                                        </button>
                                    </div>
                                </div>
                            </form>
                        )}
                    </div>

                    {/* Asistencia confirmada */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="font-semibold text-gray-900 mb-3">
                            Asistencia confirmada
                            <span className="ml-2 text-sm font-normal text-gray-500">
                                {asistenciaConfirmada} de {copropietarios.length}
                            </span>
                        </h2>
                        <div className="space-y-1 max-h-60 overflow-y-auto">
                            {copropietarios.map(c => (
                                <div key={c.id} className="flex justify-between items-center py-1.5 border-b border-gray-50">
                                    <div>
                                        <p className="text-sm font-medium text-gray-800">{c.user?.name}</p>
                                        <p className="text-xs text-gray-400">
                                            Unidad {c.unidades?.[0]?.numero ?? '—'} · {c.unidades?.[0]?.coeficiente ?? '—'}%
                                        </p>
                                    </div>
                                    {c.asistencia ? (
                                        <span className="text-xs text-green-600 font-medium">✓ Presente</span>
                                    ) : (
                                        <button
                                            onClick={() => confirmarAsistencia(c.id)}
                                            className="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition"
                                        >
                                            Confirmar
                                        </button>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                </div>

                {/* Right: Estado + Avisos */}
                <div className="space-y-4">
                    {/* State transitions */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="font-semibold text-gray-900 mb-3">Estado + Acciones</h2>
                        <div className="space-y-3">
                            <div>
                                <label className="text-xs text-gray-500 block mb-1">Observación (requerida para transiciones)</label>
                                <textarea
                                    value={observacion}
                                    onChange={e => setObservacion(e.target.value)}
                                    placeholder="Mínimo 3 caracteres..."
                                    rows={2}
                                    className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                                />
                                {transitionErrors.observacion && (
                                    <p className="text-red-500 text-xs mt-0.5">{transitionErrors.observacion}</p>
                                )}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                {(reunion.estado === 'en_curso') && (
                                    <>
                                        <button onClick={() => doTransition('suspender')}
                                            className="text-xs bg-orange-500 text-white px-3 py-1.5 rounded hover:bg-orange-600 transition">
                                            Suspender
                                        </button>
                                        <button onClick={() => doTransition('finalizar')}
                                            className="text-xs bg-gray-700 text-white px-3 py-1.5 rounded hover:bg-gray-800 transition">
                                            Finalizar
                                        </button>
                                        <button onClick={() => doTransition('cancelar')}
                                            className="text-xs bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 transition">
                                            Cancelar
                                        </button>
                                    </>
                                )}
                                {reunion.estado === 'suspendida' && (
                                    <>
                                        <button onClick={() => doTransition('reactivar')}
                                            className="text-xs bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 transition">
                                            Reactivar
                                        </button>
                                        <button onClick={() => doTransition('cancelar')}
                                            className="text-xs bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 transition">
                                            Cancelar
                                        </button>
                                    </>
                                )}
                                {reunion.estado === 'ante_sala' && (
                                    <>
                                        <button onClick={() => doTransition('iniciar')}
                                            className="text-xs bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 transition">
                                            Iniciar
                                        </button>
                                        <button onClick={() => doTransition('cancelar')}
                                            className="text-xs bg-red-600 text-white px-3 py-1.5 rounded hover:bg-red-700 transition">
                                            Cancelar
                                        </button>
                                    </>
                                )}
                                {(reunion.estado === 'convocada' || reunion.estado === 'borrador') && (
                                    <button onClick={() => doTransition('iniciar')}
                                        className="text-xs bg-green-600 text-white px-3 py-1.5 rounded hover:bg-green-700 transition">
                                        Iniciar
                                    </button>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Aviso */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="font-semibold text-gray-900 mb-3">Enviar Aviso</h2>
                        <form onSubmit={enviarAviso} className="space-y-2">
                            <input
                                type="text"
                                value={aviso}
                                onChange={e => setAviso(e.target.value)}
                                placeholder="Mensaje para todos los conectados..."
                                className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            <button type="submit"
                                className="text-xs bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 transition">
                                Enviar a todos
                            </button>
                        </form>
                    </div>

                    {/* Proyectar */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <a
                            href={`/admin/reuniones/${reunion.id}/proyeccion`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 font-medium transition"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>
                            </svg>
                            Proyectar en pantalla
                        </a>
                    </div>
                </div>
            </div>

            {/* ── ModalConectados ─────────────────────────────── */}
            {showConectados && (
                <ModalConectados
                    conectados={conectados}
                    onClose={() => setShowConectados(false)}
                />
            )}
        </AdminLayout>
    )
}
