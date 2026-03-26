import { useState, useEffect } from 'react'
import AdminLayout from '@/Layouts/AdminLayout'
import { Link, router, usePage, useForm } from '@inertiajs/react'
import { QRCodeSVG } from 'qrcode.react'
import echo from '@/echo'

const ESTADO_BADGE = {
    borrador:     'bg-gray-100 text-gray-700',
    ante_sala:    'bg-blue-100 text-blue-700',
    en_curso:     'bg-green-100 text-green-700',
    suspendida:   'bg-yellow-100 text-yellow-700',
    finalizada:   'bg-slate-100 text-slate-500',
    cancelada:    'bg-red-100 text-red-600',
    reprogramada: 'bg-purple-100 text-purple-600',
}

const VOTACION_BADGE = {
    creada:  'bg-gray-100 text-gray-600',
    abierta: 'bg-green-100 text-green-700',
    cerrada: 'bg-slate-100 text-slate-500',
    pausada: 'bg-yellow-100 text-yellow-700',
}

const VOTACION_ICON = {
    creada:  '○',
    abierta: '●',
    cerrada: '✓',
    pausada: '⏸',
}

function ModalObservacion({ titulo, onConfirm, onCancel }) {
    const [obs, setObs] = useState('')
    return (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
            <div className="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
                <p className="font-semibold text-gray-800 mb-3">{titulo}</p>
                <textarea
                    autoFocus
                    rows={3}
                    value={obs}
                    onChange={e => setObs(e.target.value)}
                    placeholder="Observación requerida (mín. 3 caracteres)..."
                    className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                />
                <div className="flex justify-end gap-2 mt-4">
                    <button onClick={onCancel}
                        className="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button onClick={() => obs.trim().length >= 3 && onConfirm(obs.trim())}
                        disabled={obs.trim().length < 3}
                        className="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 disabled:opacity-50 transition">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    )
}

const emptyForm = { pregunta: '', descripcion: '', opciones: [{ texto: '' }, { texto: '' }] }

function VotacionForm({ reunionId, votacion, onCancel }) {
    const isEditing = Boolean(votacion)
    const { data, setData, post, patch, processing, errors, reset } = useForm(
        isEditing
            ? { pregunta: votacion.pregunta, descripcion: votacion.descripcion ?? '', opciones: votacion.opciones.map(o => ({ texto: o.texto })) }
            : emptyForm
    )

    const addOpcion = () => setData('opciones', [...data.opciones, { texto: '' }])
    const removeOpcion = (i) => {
        if (data.opciones.length <= 2) return
        setData('opciones', data.opciones.filter((_, idx) => idx !== i))
    }
    const setOpcionTexto = (i, val) => {
        const opts = [...data.opciones]
        opts[i] = { texto: val }
        setData('opciones', opts)
    }

    const handleSubmit = (e) => {
        e.preventDefault()
        if (isEditing) {
            patch(`/admin/votaciones/${votacion.id}`, {
                preserveScroll: true,
                onSuccess: () => onCancel(),
            })
        } else {
            post(`/admin/reuniones/${reunionId}/votaciones`, {
                preserveScroll: true,
                onSuccess: () => { reset(); onCancel() },
            })
        }
    }

    return (
        <form onSubmit={handleSubmit} className="border border-blue-200 bg-blue-50 rounded-lg p-4 space-y-3">
            <p className="text-sm font-semibold text-blue-800">{isEditing ? 'Editar votación' : 'Nueva votación'}</p>
            <div>
                <input
                    type="text"
                    placeholder="Pregunta de la votación..."
                    value={data.pregunta}
                    onChange={e => setData('pregunta', e.target.value)}
                    className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
                {errors.pregunta && <p className="text-red-500 text-xs mt-1">{errors.pregunta}</p>}
            </div>
            <div>
                <textarea
                    placeholder="Descripción del objetivo de la votación (opcional)"
                    value={data.descripcion}
                    onChange={e => setData('descripcion', e.target.value)}
                    rows={2}
                    className="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                />
            </div>
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
                {errors['opciones'] && <p className="text-red-500 text-xs">{errors['opciones']}</p>}
            </div>
            <div className="flex gap-2 items-center">
                <button type="button" onClick={addOpcion}
                    className="text-xs text-blue-600 hover:underline">
                    + Agregar opción
                </button>
                <div className="ml-auto flex gap-2">
                    <button type="button" onClick={onCancel}
                        className="text-sm border border-gray-300 text-gray-600 px-3 py-1.5 rounded hover:bg-gray-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" disabled={processing}
                        className="text-sm bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 disabled:opacity-50 transition">
                        {isEditing ? 'Guardar' : 'Crear'}
                    </button>
                </div>
            </div>
        </form>
    )
}

export default function Show({ reunion, quorum, copropietarios = [], votaciones: initialVotaciones = [] }) {
    const { flash } = usePage().props
    const [votaciones, setVotaciones] = useState(initialVotaciones)
    const [showCreateForm, setShowCreateForm] = useState(false)
    const [editingId, setEditingId] = useState(null)
    const [modal, setModal] = useState(null) // { url, titulo }

    const accion = (url) => router.post(url, {}, { preserveScroll: true })
    const accionConObservacion = (url, titulo) => setModal({ url, titulo })
    const confirmarAccion = (observacion) => {
        const { url } = modal
        setModal(null)
        router.post(url, { observacion }, { preserveScroll: true })
    }

    const eliminarVotacion = (votacionId) => {
        if (!window.confirm('¿Eliminar esta votación?')) return
        router.delete(`/admin/votaciones/${votacionId}`, { preserveScroll: true })
    }

    useEffect(() => {
        const channel = echo.channel(`reunion.${reunion.id}`)

        channel.listen('VotacionModificada', (e) => {
            if (e.accion === 'created') {
                setVotaciones(prev => [...prev, {
                    id: e.votacion_id,
                    pregunta: e.pregunta,
                    descripcion: e.descripcion,
                    opciones: e.opciones,
                    estado: e.estado,
                }])
            } else if (e.accion === 'updated') {
                setVotaciones(prev => prev.map(v =>
                    v.id === e.votacion_id
                        ? { ...v, pregunta: e.pregunta, descripcion: e.descripcion, opciones: e.opciones, estado: e.estado }
                        : v
                ))
            } else if (e.accion === 'deleted') {
                setVotaciones(prev => prev.filter(v => v.id !== e.votacion_id))
            }
        })

        return () => echo.leave(`reunion.${reunion.id}`)
    }, [reunion.id])

    return (
        <AdminLayout title={reunion.titulo}>
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            {/* Estado y acciones */}
            <div className="bg-white rounded-lg shadow p-5 mb-6 flex flex-wrap gap-4 items-center justify-between">
                <div className="flex items-center gap-3">
                    <span className={`px-3 py-1 rounded-full text-sm font-medium ${ESTADO_BADGE[reunion.estado]}`}>
                        {reunion.estado}
                    </span>
                    <span className="text-sm text-gray-500">Tipo: {reunion.tipo} · Quórum requerido: {reunion.quorum_requerido}%</span>
                </div>
                <div className="flex gap-2 flex-wrap">
                    {reunion.estado === 'borrador' && (
                        <>
                            <button onClick={() => accion(`/admin/reuniones/${reunion.id}/convocar`)}
                                className="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                Enviar convocatoria
                            </button>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/ante-sala`, 'Observación para abrir ante sala')}
                                className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                Abrir ante sala
                            </button>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/cancelar`, 'Motivo de cancelación')}
                                className="text-sm border border-red-300 text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 transition">
                                Cancelar
                            </button>
                        </>
                    )}
                    {reunion.estado === 'ante_sala' && (
                        <>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/iniciar`, 'Observación para iniciar')}
                                className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                Iniciar reunión
                            </button>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/reprogramar`, 'Motivo de reprogramación')}
                                className="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 transition">
                                Reprogramar
                            </button>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/cancelar`, 'Motivo de cancelación')}
                                className="text-sm border border-red-300 text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 transition">
                                Cancelar
                            </button>
                        </>
                    )}
                    {reunion.estado === 'en_curso' && (
                        <>
                            <Link href={`/admin/reuniones/${reunion.id}/conducir`}
                                className="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                Panel de conducción
                            </Link>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/suspender`, 'Motivo de suspensión')}
                                className="text-sm border border-yellow-400 text-yellow-700 px-4 py-2 rounded-lg hover:bg-yellow-50 transition">
                                Suspender
                            </button>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/finalizar`, 'Observación para finalizar')}
                                className="text-sm bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                                Finalizar
                            </button>
                        </>
                    )}
                    {reunion.estado === 'suspendida' && (
                        <>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/reactivar`, 'Observación para reactivar')}
                                className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                Reactivar
                            </button>
                            <button onClick={() => accionConObservacion(`/admin/reuniones/${reunion.id}/cancelar`, 'Motivo de cancelación')}
                                className="text-sm border border-red-300 text-red-600 px-4 py-2 rounded-lg hover:bg-red-50 transition">
                                Cancelar
                            </button>
                        </>
                    )}
                    {reunion.estado === 'finalizada' && (
                        <>
                            <a href={`/admin/reuniones/${reunion.id}/reporte/pdf`}
                                className="text-sm bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                                Descargar Acta PDF
                            </a>
                            <a href={`/admin/reuniones/${reunion.id}/reporte/csv`}
                                className="text-sm bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                                CSV Asistencia
                            </a>
                            <a href={`/admin/reuniones/${reunion.id}/reporte/csv-votos`}
                                className="text-sm bg-gray-700 text-white px-4 py-2 rounded-lg hover:bg-gray-800 transition">
                                CSV Votos
                            </a>
                        </>
                    )}
                    <Link href={`/admin/reuniones/${reunion.id}/auditoria`}
                        className="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 transition">
                        Auditoría
                    </Link>
                </div>
            </div>

            {/* Quórum */}
            <div className={`rounded-lg p-5 mb-6 border ${quorum.tiene_quorum ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
                <div className="flex justify-between items-center">
                    <div>
                        <p className="font-semibold text-lg">Quórum: {quorum.porcentaje_presente}%</p>
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

            {/* QR de acceso rápido */}
            <div className="border border-sidebar-border rounded-lg p-5 mb-6">
                <h3 className="font-semibold text-gray-700 mb-4">QR de acceso rápido</h3>
                {reunion.qr_token && reunion.qr_expires_at ? (
                    <div className="flex flex-col items-center gap-4">
                        <div className="bg-white p-3 rounded inline-block shadow">
                            <QRCodeSVG value={`${window.location.origin}/sala/entrada/${reunion.qr_token}`} size={180} />
                        </div>
                        <div className="text-center">
                            <p className="text-xs text-gray-500 mb-1">URL de acceso:</p>
                            <p className="text-sm font-mono text-gray-700 break-all select-all">
                                {`${window.location.origin}/sala/entrada/${reunion.qr_token}`}
                            </p>
                        </div>
                        <p className="text-xs text-gray-500">
                            Vence: {new Date(reunion.qr_expires_at).toLocaleString('es-CO')}
                        </p>
                        <button
                            onClick={() => router.post(route('admin.reuniones.generar-qr', reunion.id))}
                            className="text-sm border border-gray-300 text-gray-600 px-4 py-2 rounded-lg hover:bg-gray-50 transition"
                        >
                            Regenerar QR
                        </button>
                    </div>
                ) : (
                    <div className="flex flex-col items-center gap-4">
                        <p className="text-sm text-gray-500">No hay QR generado</p>
                        <button
                            onClick={() => router.post(route('admin.reuniones.generar-qr', reunion.id))}
                            className="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition"
                        >
                            Generar QR de acceso
                        </button>
                    </div>
                )}
            </div>

            {/* Lista de copropietarios */}
            <div className="bg-white rounded-lg shadow overflow-hidden mb-6">
                <div className="px-5 py-4 border-b border-gray-100 font-semibold text-gray-700">
                    Copropietarios ({copropietarios.length})
                </div>
                <table className="w-full text-sm">
                    <thead className="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Nombre</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Unidad</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Coeficiente</th>
                            <th className="text-left px-4 py-3 font-medium text-gray-500">Asistencia</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {copropietarios.map(c => (
                            <tr key={c.id} className="border-b border-gray-50 hover:bg-gray-50">
                                <td className="px-4 py-3">{c.user?.name}</td>
                                <td className="px-4 py-3 text-gray-500">{(c.unidades ?? []).map(u => u.numero).join(', ') || '—'}</td>
                                <td className="px-4 py-3 text-gray-500">{((c.unidades ?? []).reduce((s, u) => s + parseFloat(u.coeficiente ?? 0), 0)).toFixed(2)}%</td>
                                <td className="px-4 py-3">
                                    {c.asistencia ? (
                                        <span className="text-green-600 text-xs font-medium">✓ Presente</span>
                                    ) : (
                                        <span className="text-gray-400 text-xs">Ausente</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {!c.asistencia && reunion.estado === 'en_curso' && (
                                        <button
                                            onClick={() => accion(`/admin/reuniones/${reunion.id}/copropietarios/${c.id}/asistencia`)}
                                            className="text-xs bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700 transition"
                                        >
                                            Confirmar
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Votaciones */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
                <div className="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <span className="font-semibold text-gray-700">Votaciones ({votaciones.length})</span>
                    {!showCreateForm && (
                        <button
                            onClick={() => { setShowCreateForm(true); setEditingId(null) }}
                            className="text-sm bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 transition"
                        >
                            + Nueva votación
                        </button>
                    )}
                </div>

                <div className="p-4 space-y-3">
                    {showCreateForm && (
                        <VotacionForm
                            reunionId={reunion.id}
                            votacion={null}
                            onCancel={() => setShowCreateForm(false)}
                        />
                    )}

                    {votaciones.length === 0 && !showCreateForm && (
                        <p className="text-sm text-gray-400 text-center py-4">No hay votaciones creadas.</p>
                    )}

                    {votaciones.map(v => (
                        <div key={v.id}>
                            {editingId === v.id ? (
                                <VotacionForm
                                    reunionId={reunion.id}
                                    votacion={v}
                                    onCancel={() => setEditingId(null)}
                                />
                            ) : (
                                <div className="flex items-center gap-3 py-2 px-1 border-b border-gray-50 last:border-0">
                                    <span className="text-base text-gray-400 w-5 text-center flex-shrink-0">
                                        {VOTACION_ICON[v.estado] ?? '○'}
                                    </span>
                                    <span className={`text-xs px-2 py-0.5 rounded-full font-medium flex-shrink-0 ${VOTACION_BADGE[v.estado] ?? 'bg-gray-100 text-gray-600'}`}>
                                        {v.estado.toUpperCase().slice(0, 4)}
                                    </span>
                                    <div className="flex-1 min-w-0">
                                        <span className="text-sm text-gray-800 truncate block">{v.pregunta}</span>
                                        {v.descripcion && <span className="text-xs text-gray-400 truncate block">{v.descripcion}</span>}
                                    </div>
                                    {v.estado === 'creada' && (
                                        <div className="flex gap-2 flex-shrink-0">
                                            <button
                                                onClick={() => { setEditingId(v.id); setShowCreateForm(false) }}
                                                className="text-xs border border-gray-300 text-gray-600 px-3 py-1 rounded hover:bg-gray-50 transition"
                                            >
                                                Editar
                                            </button>
                                            <button
                                                onClick={() => eliminarVotacion(v.id)}
                                                className="text-xs border border-red-200 text-red-600 px-3 py-1 rounded hover:bg-red-50 transition"
                                            >
                                                Eliminar
                                            </button>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>
            {modal && (
                <ModalObservacion
                    titulo={modal.titulo}
                    onConfirm={confirmarAccion}
                    onCancel={() => setModal(null)}
                />
            )}
        </AdminLayout>
    )
}
