import { useState, useEffect } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AdminLayout from '@/Layouts/AdminLayout'

const ESTADO_COLOR = {
    pendiente: 'bg-yellow-100 text-yellow-700',
    aprobado:  'bg-green-100 text-green-700',
    rechazado: 'bg-red-100 text-red-700',
    revocado:  'bg-gray-100 text-gray-600',
    expirado:  'bg-slate-100 text-slate-500',
}

function PoderRow({ poder, onAprobar, onRechazar, onRevocar }) {
    const apoderado = poder.apoderado
    const poderdante = poder.poderdante
    return (
        <div className="flex items-start justify-between py-3 border-b border-gray-100 gap-3 last:border-0">
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                    <p className="text-sm font-medium text-gray-800 truncate">{apoderado?.user?.name}</p>
                    {apoderado?.es_externo && (
                        <span className="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded">Externo</span>
                    )}
                    {apoderado?.empresa && (
                        <span className="text-xs text-gray-400 truncate">{apoderado.empresa}</span>
                    )}
                </div>
                <p className="text-xs text-gray-400">
                    Delegado por: <span className="text-gray-600">{poderdante?.user?.name}</span>
                    {' · '}
                    {poderdante?.unidades?.map(u => u.numero).join(', ') || '—'}
                </p>
                {poder.invitacion_enviada_at && (
                    <p className="text-[10px] text-green-600 mt-0.5">✓ Notificación enviada</p>
                )}
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
                <span className={`text-[10px] font-bold px-2 py-0.5 rounded capitalize ${ESTADO_COLOR[poder.estado]}`}>
                    {poder.estado}
                </span>
                {poder.estado === 'pendiente' && (
                    <>
                        <button onClick={() => onAprobar(poder.id)} className="text-xs bg-green-600 text-white px-2.5 py-1 rounded hover:bg-green-700 transition">
                            Aprobar
                        </button>
                        <button onClick={() => onRechazar(poder.id)} className="text-xs text-red-500 hover:text-red-700 px-1">
                            Rechazar
                        </button>
                    </>
                )}
                {poder.estado === 'aprobado' && (
                    <button onClick={() => onRevocar(poder.id)} className="text-xs text-gray-400 hover:text-red-600 px-1">
                        Revocar
                    </button>
                )}
            </div>
        </div>
    )
}

function CrearPoderForm({ copropietarios, onSuccess }) {
    const [modo, setModo] = useState('copropietario') // 'copropietario' | 'externo'
    const [busqueda, setBusqueda] = useState('')
    const [apoderadoSeleccionado, setApoderadoSeleccionado] = useState(null)
    const [elegibilidad, setElegibilidad] = useState(null)
    const [verificando, setVerificando] = useState(false)

    const { data, setData, post, processing, errors, reset } = useForm({
        poderdante_id:               '',
        apoderado_copropietario_id:  '',
        delegado_nombre:             '',
        delegado_email:              '',
        delegado_documento:          '',
        delegado_telefono:           '',
        delegado_empresa:            '',
        documento_url:               '',
    })

    const copropietariosFiltrados = copropietarios.filter(c => {
        const q = busqueda.toLowerCase()
        return (
            c.user?.name?.toLowerCase().includes(q) ||
            (c.numero_documento ?? '').toLowerCase().includes(q) ||
            (c.unidades ?? []).some(u => u.numero?.toLowerCase().includes(q))
        )
    })

    useEffect(() => {
        if (!apoderadoSeleccionado) { setElegibilidad(null); return }
        setVerificando(true)
        fetch(`/admin/poderes/verificar-delegado?copropietario_id=${apoderadoSeleccionado.id}`)
            .then(r => r.json())
            .then(d => { setElegibilidad(d); setVerificando(false) })
            .catch(() => setVerificando(false))
    }, [apoderadoSeleccionado])

    const seleccionar = (c) => {
        setApoderadoSeleccionado(c)
        setData('apoderado_copropietario_id', c.id)
        setBusqueda('')
    }

    const limpiarSeleccion = () => {
        setApoderadoSeleccionado(null)
        setElegibilidad(null)
        setData('apoderado_copropietario_id', '')
    }

    const cambiarModo = (m) => {
        setModo(m)
        limpiarSeleccion()
        reset()
    }

    const submit = (e) => {
        e.preventDefault()
        post('/admin/poderes', {
            preserveScroll: true,
            onSuccess: () => { reset(); limpiarSeleccion(); onSuccess() },
        })
    }

    const puedeEnviar = () => {
        if (!data.poderdante_id) return false
        if (modo === 'copropietario') return !!apoderadoSeleccionado && elegibilidad?.elegible
        return true
    }

    return (
        <form onSubmit={submit} className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 space-y-4">
            <h3 className="font-semibold text-gray-800 text-sm">Nuevo poder</h3>

            {/* Poderdante */}
            <div>
                <label className="text-xs text-gray-500 block mb-1">Copropietario que otorga el poder (poderdante) *</label>
                <select
                    value={data.poderdante_id}
                    onChange={e => setData('poderdante_id', e.target.value)}
                    className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                    <option value="">Seleccionar copropietario…</option>
                    {copropietarios.map(c => (
                        <option key={c.id} value={c.id}>
                            {c.user?.name} — {c.unidades?.map(u => u.numero).join(', ') || 'sin unidades'}
                        </option>
                    ))}
                </select>
                {errors.poderdante_id && <p className="text-red-500 text-xs mt-0.5">{errors.poderdante_id}</p>}
            </div>

            {/* Toggle modo */}
            <div>
                <label className="text-xs text-gray-500 block mb-2">Tipo de delegado *</label>
                <div className="flex rounded-lg border border-gray-300 overflow-hidden text-sm">
                    <button
                        type="button"
                        onClick={() => cambiarModo('copropietario')}
                        className={`flex-1 px-3 py-1.5 transition ${modo === 'copropietario' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
                    >
                        Copropietario del conjunto
                    </button>
                    <button
                        type="button"
                        onClick={() => cambiarModo('externo')}
                        className={`flex-1 px-3 py-1.5 transition border-l border-gray-300 ${modo === 'externo' ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'}`}
                    >
                        Delegado externo
                    </button>
                </div>
            </div>

            {/* Modo A: copropietario */}
            {modo === 'copropietario' && (
                <div>
                    {!apoderadoSeleccionado ? (
                        <>
                            <label className="text-xs text-gray-500 block mb-1">Buscar copropietario *</label>
                            <input
                                type="text"
                                value={busqueda}
                                onChange={e => setBusqueda(e.target.value)}
                                placeholder="Nombre, documento o unidad…"
                                className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-1"
                            />
                            {busqueda.length > 0 && (
                                <div className="border border-gray-200 rounded bg-white max-h-40 overflow-y-auto shadow-sm">
                                    {copropietariosFiltrados.length === 0 ? (
                                        <p className="text-xs text-gray-400 px-3 py-2">Sin resultados</p>
                                    ) : copropietariosFiltrados.map(c => (
                                        <button
                                            key={c.id}
                                            type="button"
                                            onClick={() => seleccionar(c)}
                                            className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 transition border-b border-gray-100 last:border-0"
                                        >
                                            <span className="font-medium">{c.user?.name}</span>
                                            <span className="text-gray-400 text-xs ml-2">
                                                {c.numero_documento && `Doc: ${c.numero_documento} · `}
                                                {c.unidades?.map(u => u.numero).join(', ')}
                                            </span>
                                        </button>
                                    ))}
                                </div>
                            )}
                        </>
                    ) : (
                        <div>
                            <div className="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2.5 bg-white">
                                <div>
                                    <p className="text-sm font-medium text-gray-800">{apoderadoSeleccionado.user?.name}</p>
                                    <p className="text-xs text-gray-400 mt-0.5">
                                        {apoderadoSeleccionado.numero_documento && `Doc: ${apoderadoSeleccionado.numero_documento} · `}
                                        Unidades: {apoderadoSeleccionado.unidades?.map(u => u.numero).join(', ') || '—'}
                                    </p>
                                </div>
                                <button type="button" onClick={limpiarSeleccion} className="text-xs text-gray-400 hover:text-red-500 ml-3">✕</button>
                            </div>

                            {verificando && <p className="text-xs text-gray-400 mt-1.5">Verificando elegibilidad…</p>}

                            {elegibilidad && !verificando && (
                                <div className={`mt-1.5 px-3 py-2 rounded text-xs font-medium ${
                                    elegibilidad.bloqueado
                                        ? 'bg-red-50 border border-red-200 text-red-700'
                                        : elegibilidad.info
                                            ? 'bg-yellow-50 border border-yellow-200 text-yellow-700'
                                            : 'bg-green-50 border border-green-200 text-green-700'
                                }`}>
                                    {elegibilidad.bloqueado
                                        ? `✕ ${elegibilidad.motivo}`
                                        : elegibilidad.info
                                            ? `ⓘ ${elegibilidad.info}`
                                            : '✓ Elegible como delegado'}
                                </div>
                            )}
                        </div>
                    )}
                    {errors.apoderado_copropietario_id && (
                        <p className="text-red-500 text-xs mt-0.5">{errors.apoderado_copropietario_id}</p>
                    )}
                </div>
            )}

            {/* Modo B: externo */}
            {modo === 'externo' && (
                <div className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Nombre completo *</label>
                            <input type="text" value={data.delegado_nombre} onChange={e => setData('delegado_nombre', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Nombre del delegado" />
                            {errors.delegado_nombre && <p className="text-red-500 text-xs mt-0.5">{errors.delegado_nombre}</p>}
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Email *</label>
                            <input type="email" value={data.delegado_email} onChange={e => setData('delegado_email', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="correo@ejemplo.com" />
                            {errors.delegado_email && <p className="text-red-500 text-xs mt-0.5">{errors.delegado_email}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">N.º documento *</label>
                            <input type="text" value={data.delegado_documento} onChange={e => setData('delegado_documento', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Requerido" />
                            {errors.delegado_documento && <p className="text-red-500 text-xs mt-0.5">{errors.delegado_documento}</p>}
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Teléfono</label>
                            <input type="text" value={data.delegado_telefono} onChange={e => setData('delegado_telefono', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Opcional" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Empresa</label>
                            <input type="text" value={data.delegado_empresa} onChange={e => setData('delegado_empresa', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Opcional" />
                        </div>
                    </div>
                </div>
            )}

            <div className="flex justify-end gap-2 pt-1">
                <button type="button" onClick={onSuccess} className="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5">Cancelar</button>
                <button type="submit" disabled={processing || !puedeEnviar()}
                    className="text-sm bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 disabled:opacity-40 transition">
                    {processing ? 'Guardando…' : 'Guardar'}
                </button>
            </div>
        </form>
    )
}

export default function PoderesIndex({ poderes = {}, copropietarios = [] }) {
    const { flash } = usePage().props
    const [tab, setTab] = useState('pendiente')
    const [showCrear, setShowCrear] = useState(false)

    const tabs = ['pendiente', 'aprobado', 'rechazado', 'revocado', 'expirado']
    const counts = tabs.reduce((acc, t) => ({ ...acc, [t]: (poderes[t] ?? []).length }), {})
    const listaTab = poderes[tab] ?? []

    const handleAprobar = (poderId) => {
        router.patch(`/admin/poderes/${poderId}/aprobar`, {}, { preserveScroll: true })
    }
    const handleRechazar = (poderId) => {
        const motivo = window.prompt('Motivo del rechazo (opcional):')
        if (motivo === null) return
        router.patch(`/admin/poderes/${poderId}/rechazar`, { motivo }, { preserveScroll: true })
    }
    const handleRevocar = (poderId) => {
        if (!window.confirm('¿Revocar este poder?')) return
        router.delete(`/admin/poderes/${poderId}`, { preserveScroll: true })
    }

    return (
        <AdminLayout title="Poderes">
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            <p className="text-sm text-app-text-muted mb-4">
                Los poderes activos permiten que un delegado vote en nombre de un copropietario.
                Se expiran automáticamente al finalizar una reunión.
            </p>

            {/* Tabs */}
            <div className="flex gap-1 mb-4 border-b border-gray-200 pb-0 flex-wrap">
                {tabs.map(t => (
                    <button key={t} onClick={() => setTab(t)}
                        className={`px-4 py-2 text-sm font-medium capitalize border-b-2 transition -mb-px ${
                            tab === t ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'
                        }`}
                    >
                        {t}
                        {counts[t] > 0 && (
                            <span className={`ml-1.5 text-xs rounded-full px-1.5 py-0.5 ${tab === t ? 'bg-blue-100' : 'bg-gray-100'}`}>
                                {counts[t]}
                            </span>
                        )}
                    </button>
                ))}
                <div className="ml-auto">
                    <button onClick={() => setShowCrear(!showCrear)}
                        className="text-sm bg-blue-600 text-white px-4 py-1.5 rounded-lg hover:bg-blue-700 transition">
                        + Crear poder
                    </button>
                </div>
            </div>

            {showCrear && (
                <CrearPoderForm
                    copropietarios={copropietarios}
                    onSuccess={() => setShowCrear(false)}
                />
            )}

            <div className="bg-white rounded-lg shadow p-4">
                {listaTab.length === 0 ? (
                    <p className="text-sm text-gray-400 py-6 text-center">No hay poderes con estado "{tab}".</p>
                ) : (
                    listaTab.map(poder => (
                        <PoderRow key={poder.id} poder={poder}
                            onAprobar={handleAprobar} onRechazar={handleRechazar} onRevocar={handleRevocar} />
                    ))
                )}
            </div>
        </AdminLayout>
    )
}
