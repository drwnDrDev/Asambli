import { useState, useEffect } from 'react'
import { useForm, Link } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'

export default function Create({ yaActivo = false, copropietarios = [] }) {
    const [modo, setModo] = useState('copropietario') // 'copropietario' | 'externo'
    const [busqueda, setBusqueda] = useState('')
    const [apoderadoSeleccionado, setApoderadoSeleccionado] = useState(null)
    const [elegibilidad, setElegibilidad] = useState(null)
    const [verificando, setVerificando] = useState(false)

    const { data, setData, post, processing, errors, reset } = useForm({
        apoderado_copropietario_id: '',
        delegado_nombre:            '',
        delegado_email:             '',
        delegado_documento:         '',
        delegado_telefono:          '',
        delegado_empresa:           '',
    })

    const filtrados = copropietarios.filter(c => {
        const q = busqueda.toLowerCase()
        return (
            (c.nombre ?? '').toLowerCase().includes(q) ||
            (c.numero_documento ?? '').toLowerCase().includes(q) ||
            (c.unidades ?? []).some(u => u.numero?.toLowerCase().includes(q))
        )
    })

    useEffect(() => {
        if (!apoderadoSeleccionado) { setElegibilidad(null); return }
        setVerificando(true)
        fetch(`/sala/poderes/verificar-delegado?copropietario_id=${apoderadoSeleccionado.id}`)
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

    const puedeEnviar = () => {
        if (modo === 'copropietario') return !!apoderadoSeleccionado && elegibilidad?.elegible
        return true
    }

    const submit = (e) => {
        e.preventDefault()
        post('/sala/poderes')
    }

    if (yaActivo) {
        return (
            <SalaLayout>
                <div className="max-w-md mx-auto text-center py-12">
                    <div className="text-4xl mb-4">⚠️</div>
                    <h2 className="text-lg font-semibold mb-2">Ya tienes un poder activo</h2>
                    <p className="text-slate-400 text-sm mb-6">
                        Solo puedes tener un poder activo a la vez. Retíralo antes de registrar uno nuevo.
                    </p>
                    <Link
                        href="/sala/poderes"
                        className="inline-block bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold px-6 py-2.5 rounded-xl text-sm transition"
                    >
                        Ver mi poder
                    </Link>
                </div>
            </SalaLayout>
        )
    }

    return (
        <SalaLayout>
            <div className="max-w-md mx-auto">
                <Link href="/sala/poderes" className="text-sm text-slate-400 hover:text-slate-300 mb-4 inline-block">
                    ← Volver
                </Link>
                <h1 className="text-xl font-bold mb-1">Registrar poder</h1>
                <p className="text-slate-400 text-sm mb-6">
                    Autoriza a una persona para votar en tu nombre en la próxima reunión.
                </p>

                <div className="bg-slate-800 rounded-xl p-4 mb-6 text-sm text-slate-300">
                    Una vez aprobado por el administrador, tu acceso a la sala quedará bloqueado y tu delegado podrá votar en tu nombre.
                </div>

                <form onSubmit={submit} className="space-y-5">
                    {/* Toggle modo */}
                    <div>
                        <label className="block text-xs text-slate-400 mb-2">Tipo de delegado *</label>
                        <div className="flex rounded-xl border border-slate-600 overflow-hidden text-sm">
                            <button
                                type="button"
                                onClick={() => cambiarModo('copropietario')}
                                className={`flex-1 px-3 py-2 transition ${modo === 'copropietario' ? 'bg-amber-500 text-slate-900 font-semibold' : 'bg-slate-800 text-slate-400 hover:bg-slate-700'}`}
                            >
                                Copropietario del conjunto
                            </button>
                            <button
                                type="button"
                                onClick={() => cambiarModo('externo')}
                                className={`flex-1 px-3 py-2 transition border-l border-slate-600 ${modo === 'externo' ? 'bg-amber-500 text-slate-900 font-semibold' : 'bg-slate-800 text-slate-400 hover:bg-slate-700'}`}
                            >
                                Delegado externo
                            </button>
                        </div>
                    </div>

                    {/* Modo A: copropietario del conjunto */}
                    {modo === 'copropietario' && (
                        <div>
                            {!apoderadoSeleccionado ? (
                                <>
                                    <label className="block text-xs text-slate-400 mb-1">Buscar copropietario *</label>
                                    <input
                                        type="text"
                                        value={busqueda}
                                        onChange={e => setBusqueda(e.target.value)}
                                        placeholder="Nombre, documento o unidad…"
                                        className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500 placeholder-slate-500"
                                    />
                                    {busqueda.length > 0 && (
                                        <div className="mt-1 border border-slate-600 rounded-lg bg-slate-800 max-h-44 overflow-y-auto shadow-lg">
                                            {filtrados.length === 0 ? (
                                                <p className="text-xs text-slate-400 px-3 py-2">Sin resultados</p>
                                            ) : filtrados.map(c => (
                                                <button
                                                    key={c.id}
                                                    type="button"
                                                    onClick={() => seleccionar(c)}
                                                    className="w-full text-left px-3 py-2 text-sm hover:bg-slate-700 transition border-b border-slate-700 last:border-0"
                                                >
                                                    <span className="font-medium text-white">{c.nombre}</span>
                                                    <span className="text-slate-400 text-xs ml-2">
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
                                    <div className="flex items-center justify-between border border-slate-600 rounded-lg px-3 py-2.5 bg-slate-800">
                                        <div>
                                            <p className="text-sm font-medium text-white">{apoderadoSeleccionado.nombre}</p>
                                            <p className="text-xs text-slate-400 mt-0.5">
                                                {apoderadoSeleccionado.numero_documento && `Doc: ${apoderadoSeleccionado.numero_documento} · `}
                                                Unidades: {apoderadoSeleccionado.unidades?.map(u => u.numero).join(', ') || '—'}
                                            </p>
                                        </div>
                                        <button type="button" onClick={limpiarSeleccion} className="text-slate-400 hover:text-red-400 ml-3 text-sm">✕</button>
                                    </div>

                                    {verificando && (
                                        <p className="text-xs text-slate-400 mt-2">Verificando elegibilidad…</p>
                                    )}

                                    {elegibilidad && !verificando && (
                                        <div className={`mt-2 px-3 py-2 rounded-lg text-xs font-medium ${
                                            elegibilidad.bloqueado
                                                ? 'bg-red-900/40 border border-red-700 text-red-300'
                                                : elegibilidad.info
                                                    ? 'bg-yellow-900/40 border border-yellow-700 text-yellow-300'
                                                    : 'bg-green-900/40 border border-green-700 text-green-300'
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
                                <p className="text-red-400 text-xs mt-1">{errors.apoderado_copropietario_id}</p>
                            )}
                        </div>
                    )}

                    {/* Modo B: externo */}
                    {modo === 'externo' && (
                        <div className="space-y-4">
                            <div>
                                <label className="block text-xs text-slate-400 mb-1">Nombre completo *</label>
                                <input
                                    type="text"
                                    value={data.delegado_nombre}
                                    onChange={e => setData('delegado_nombre', e.target.value)}
                                    className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    placeholder="Nombre del delegado"
                                />
                                {errors.delegado_nombre && <p className="text-red-400 text-xs mt-1">{errors.delegado_nombre}</p>}
                            </div>

                            <div>
                                <label className="block text-xs text-slate-400 mb-1">Correo electrónico *</label>
                                <input
                                    type="email"
                                    value={data.delegado_email}
                                    onChange={e => setData('delegado_email', e.target.value)}
                                    className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    placeholder="correo@ejemplo.com"
                                />
                                {errors.delegado_email && <p className="text-red-400 text-xs mt-1">{errors.delegado_email}</p>}
                            </div>

                            <div>
                                <label className="block text-xs text-slate-400 mb-1">Número de documento *</label>
                                <input
                                    type="text"
                                    value={data.delegado_documento}
                                    onChange={e => setData('delegado_documento', e.target.value)}
                                    className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                    placeholder="Cédula u otro documento"
                                />
                                {errors.delegado_documento && <p className="text-red-400 text-xs mt-1">{errors.delegado_documento}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-xs text-slate-400 mb-1">Teléfono</label>
                                    <input
                                        type="text"
                                        value={data.delegado_telefono}
                                        onChange={e => setData('delegado_telefono', e.target.value)}
                                        className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        placeholder="Opcional"
                                    />
                                </div>
                                <div>
                                    <label className="block text-xs text-slate-400 mb-1">Empresa</label>
                                    <input
                                        type="text"
                                        value={data.delegado_empresa}
                                        onChange={e => setData('delegado_empresa', e.target.value)}
                                        className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                        placeholder="Opcional"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={processing || !puedeEnviar()}
                        className="w-full bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold py-2.5 rounded-xl text-sm transition disabled:opacity-40"
                    >
                        {processing ? 'Enviando…' : 'Enviar solicitud de poder'}
                    </button>
                </form>
            </div>
        </SalaLayout>
    )
}
