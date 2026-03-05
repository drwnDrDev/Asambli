import { useState, useEffect } from 'react'
import { router, usePage, useForm } from '@inertiajs/react'
import AdminLayout from '@/Layouts/AdminLayout'
import echo from '@/echo'

export default function Conducir({ reunion, quorum: initialQuorum, copropietarios = [], votaciones: initialVotaciones = [] }) {
    const { flash } = usePage().props
    const [quorum, setQuorum] = useState(initialQuorum)
    const [votaciones, setVotaciones] = useState(initialVotaciones)
    const [resultados, setResultados] = useState({})

    // Formulario nueva votación
    const { data, setData, post, processing, reset, errors } = useForm({
        pregunta: '',
        opciones: [{ texto: 'Sí' }, { texto: 'No' }, { texto: 'Abstención' }],
    })

    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel.listen('.QuorumActualizado', (e) => {
            setQuorum(e.quorumData)
        })
        channel.listen('.EstadoVotacionCambiado', (e) => {
            setVotaciones(prev => prev.map(v =>
                v.id === e.votacion_id ? { ...v, estado: e.estado } : v
            ))
        })
        channel.listen('.ResultadosVotacionActualizados', (e) => {
            setResultados(prev => ({ ...prev, [e.votacion_id]: e.resultados }))
        })

        return () => echo.leave(`reunion.${reunion.id}`)
    }, [reunion.id])

    const confirmarAsistencia = (copropietarioId) =>
        router.post(`/admin/reuniones/${reunion.id}/copropietarios/${copropietarioId}/asistencia`, {}, { preserveScroll: true })

    const submitVotacion = (e) => {
        e.preventDefault()
        post(`/admin/reuniones/${reunion.id}/votaciones`, {
            preserveScroll: true,
            onSuccess: () => reset(),
        })
    }

    const abrirVotacion = (votacionId) =>
        router.post(`/admin/votaciones/${votacionId}/abrir`, {}, { preserveScroll: true })

    const cerrarVotacion = (votacionId) =>
        router.post(`/admin/votaciones/${votacionId}/cerrar`, {}, { preserveScroll: true })

    const addOpcion = () => setData('opciones', [...data.opciones, { texto: '' }])
    const removeOpcion = (i) => setData('opciones', data.opciones.filter((_, idx) => idx !== i))
    const setOpcionTexto = (i, val) => {
        const opts = [...data.opciones]
        opts[i] = { texto: val }
        setData('opciones', opts)
    }

    return (
        <AdminLayout title={`Conducción — ${reunion.titulo}`}>
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            {/* Quórum en tiempo real */}
            <div className={`rounded-lg p-4 mb-6 border ${quorum.tiene_quorum ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
                <div className="flex justify-between items-center">
                    <div>
                        <p className="font-semibold text-lg">
                            Quórum: {quorum.porcentaje_presente}%
                        </p>
                        <p className="text-sm text-gray-600">
                            Requerido: {quorum.quorum_requerido}% · {quorum.presente} de {quorum.total}{' '}
                            {quorum.tipo === 'coeficiente' ? 'puntos de coeficiente' : 'unidades'}
                        </p>
                    </div>
                    <span className={`text-xl font-bold ${quorum.tiene_quorum ? 'text-green-700' : 'text-red-700'}`}>
                        {quorum.tiene_quorum ? '✓ HAY QUÓRUM' : '✗ SIN QUÓRUM'}
                    </span>
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Asistencia */}
                <div className="bg-white rounded-lg shadow p-4">
                    <h2 className="font-semibold text-gray-900 mb-3">Asistencia</h2>
                    <div className="space-y-1 max-h-80 overflow-y-auto">
                        {copropietarios.map(c => (
                            <div key={c.id} className="flex justify-between items-center py-2 border-b border-gray-50">
                                <div>
                                    <p className="text-sm font-medium">{c.user?.name}</p>
                                    <p className="text-xs text-gray-400">
                                        Unidad {c.unidad?.numero} · {c.unidad?.coeficiente}%
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

                {/* Votaciones */}
                <div className="space-y-4">
                    {/* Nueva votación */}
                    <div className="bg-white rounded-lg shadow p-4">
                        <h2 className="font-semibold text-gray-900 mb-3">Nueva votación</h2>
                        <form onSubmit={submitVotacion} className="space-y-3">
                            <input
                                type="text"
                                placeholder="Pregunta de la votación..."
                                value={data.pregunta}
                                onChange={e => setData('pregunta', e.target.value)}
                                className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            />
                            {errors.pregunta && <p className="text-red-500 text-xs">{errors.pregunta}</p>}
                            <div className="space-y-2">
                                {data.opciones.map((op, i) => (
                                    <div key={i} className="flex gap-2">
                                        <input
                                            type="text"
                                            value={op.texto}
                                            onChange={e => setOpcionTexto(i, e.target.value)}
                                            placeholder={`Opción ${i + 1}`}
                                            className="flex-1 border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-400"
                                        />
                                        {data.opciones.length > 2 && (
                                            <button type="button" onClick={() => removeOpcion(i)}
                                                className="text-red-400 hover:text-red-600 text-sm px-2">✕</button>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <div className="flex gap-2">
                                <button type="button" onClick={addOpcion}
                                    className="text-xs text-blue-600 hover:underline">
                                    + Agregar opción
                                </button>
                                <button type="submit" disabled={processing}
                                    className="ml-auto text-sm bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 disabled:opacity-50 transition">
                                    Crear
                                </button>
                            </div>
                        </form>
                    </div>

                    {/* Lista de votaciones */}
                    {votaciones.map(v => {
                        const res = resultados[v.id]
                        return (
                            <div key={v.id} className="bg-white rounded-lg shadow p-4">
                                <div className="flex justify-between items-start mb-2">
                                    <p className="font-medium text-sm">{v.pregunta}</p>
                                    <span className={`text-xs px-2 py-0.5 rounded-full ${
                                        v.estado === 'abierta' ? 'bg-green-100 text-green-700' :
                                        v.estado === 'cerrada' ? 'bg-gray-100 text-gray-500' :
                                        'bg-yellow-100 text-yellow-700'
                                    }`}>
                                        {v.estado}
                                    </span>
                                </div>
                                {res && (
                                    <div className="mb-3 space-y-1">
                                        {res.map((r, i) => (
                                            <div key={i} className="flex justify-between text-xs text-gray-600">
                                                <span>{r.texto}</span>
                                                <span>{r.count} votos ({r.peso_total?.toFixed(2)}%)</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                                <div className="flex gap-2">
                                    {v.estado === 'pendiente' && (
                                        <button onClick={() => abrirVotacion(v.id)}
                                            className="text-xs bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700 transition">
                                            Abrir
                                        </button>
                                    )}
                                    {v.estado === 'abierta' && (
                                        <button onClick={() => cerrarVotacion(v.id)}
                                            className="text-xs bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 transition">
                                            Cerrar votación
                                        </button>
                                    )}
                                    <a href={`/admin/votaciones/${v.id}/resultados`}
                                        className="text-xs text-blue-600 hover:underline ml-auto">
                                        Ver resultados
                                    </a>
                                </div>
                            </div>
                        )
                    })}
                </div>
            </div>
        </AdminLayout>
    )
}
