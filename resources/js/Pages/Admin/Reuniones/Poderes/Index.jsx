import { useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AdminLayout from '@/Layouts/AdminLayout'
import { Link } from '@inertiajs/react'

const ESTADO_COLOR = {
    pendiente: 'bg-yellow-100 text-yellow-700',
    aprobado:  'bg-green-100 text-green-700',
    rechazado: 'bg-red-100 text-red-700',
    revocado:  'bg-gray-100 text-gray-600',
}

function PoderRow({ poder, reunionId, onAprobar, onRechazar, onRevocar }) {
    const apoderado = poder.apoderado
    const poderdante = poder.poderdante

    return (
        <div className="flex items-start justify-between py-3 border-b border-gray-100 gap-3">
            <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                    <p className="text-sm font-medium text-gray-800 truncate">{apoderado?.user?.name}</p>
                    {apoderado?.es_externo && (
                        <span className="text-[10px] font-bold bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded flex-shrink-0">D</span>
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
                    <p className="text-[10px] text-green-600 mt-0.5">✓ Invitación enviada</p>
                )}
            </div>
            <div className="flex items-center gap-2 flex-shrink-0">
                <span className={`text-[10px] font-bold px-2 py-0.5 rounded capitalize ${ESTADO_COLOR[poder.estado]}`}>
                    {poder.estado}
                </span>
                {poder.estado === 'pendiente' && (
                    <>
                        <button
                            onClick={() => onAprobar(poder.id)}
                            className="text-xs bg-green-600 text-white px-2.5 py-1 rounded hover:bg-green-700 transition"
                        >
                            Aprobar
                        </button>
                        <button
                            onClick={() => onRechazar(poder.id)}
                            className="text-xs text-red-500 hover:text-red-700 px-1"
                        >
                            Rechazar
                        </button>
                    </>
                )}
                {poder.estado === 'aprobado' && (
                    <button
                        onClick={() => onRevocar(poder.id)}
                        className="text-xs text-gray-400 hover:text-red-600 px-1"
                    >
                        Revocar
                    </button>
                )}
            </div>
        </div>
    )
}

export default function PoderesIndex({ reunion, poderes = {}, copropietarios = [] }) {
    const { flash } = usePage().props
    const [tab, setTab] = useState('pendiente')
    const [showCrear, setShowCrear] = useState(false)

    const { data, setData, post, processing, errors, reset } = useForm({
        poderdante_id:    '',
        delegado_nombre:  '',
        delegado_email:   '',
        delegado_telefono:'',
        delegado_documento:'',
        delegado_empresa: '',
        documento_url:    '',
    })

    const listaTab = poderes[tab] ?? []

    const handleAprobar = (poderId) => {
        router.patch(`/admin/reuniones/${reunion.id}/poderes/${poderId}/aprobar`, {}, { preserveScroll: true })
    }

    const handleRechazar = (poderId) => {
        const motivo = window.prompt('Motivo del rechazo (opcional):')
        if (motivo === null) return
        router.patch(`/admin/reuniones/${reunion.id}/poderes/${poderId}/rechazar`, { motivo }, { preserveScroll: true })
    }

    const handleRevocar = (poderId) => {
        if (!window.confirm('¿Revocar este poder?')) return
        router.delete(`/admin/reuniones/${reunion.id}/poderes/${poderId}`, { preserveScroll: true })
    }

    const submitCrear = (e) => {
        e.preventDefault()
        post(`/admin/reuniones/${reunion.id}/poderes`, {
            preserveScroll: true,
            onSuccess: () => { reset(); setShowCrear(false) },
        })
    }

    const tabs = ['pendiente', 'aprobado', 'rechazado', 'revocado']
    const counts = tabs.reduce((acc, t) => ({ ...acc, [t]: (poderes[t] ?? []).length }), {})

    return (
        <AdminLayout title={`Poderes — ${reunion.titulo}`}>
            <div className="mb-4 flex items-center gap-3">
                <Link href={`/admin/reuniones/${reunion.id}/conducir`} className="text-sm text-gray-500 hover:text-gray-700">
                    ← Conducir
                </Link>
                <h1 className="text-lg font-semibold text-gray-900">Poderes</h1>
                <span className="text-sm text-gray-500">{reunion.titulo}</span>
            </div>

            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            {/* Tabs */}
            <div className="flex gap-2 mb-4 border-b border-gray-200 pb-0">
                {tabs.map(t => (
                    <button
                        key={t}
                        onClick={() => setTab(t)}
                        className={`px-4 py-2 text-sm font-medium capitalize border-b-2 transition -mb-px ${
                            tab === t
                                ? 'border-blue-600 text-blue-600'
                                : 'border-transparent text-gray-500 hover:text-gray-700'
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
                    <button
                        onClick={() => setShowCrear(!showCrear)}
                        className="text-sm bg-blue-600 text-white px-4 py-1.5 rounded-lg hover:bg-blue-700 transition"
                    >
                        + Crear poder
                    </button>
                </div>
            </div>

            {/* Crear poder form */}
            {showCrear && (
                <form onSubmit={submitCrear} className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6 space-y-3">
                    <h3 className="font-semibold text-gray-800 text-sm">Nuevo poder</h3>
                    <div>
                        <label className="text-xs text-gray-500 block mb-1">Poderdante (copropietario que otorga el poder) *</label>
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
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Nombre del delegado *</label>
                            <input type="text" value={data.delegado_nombre} onChange={e => setData('delegado_nombre', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder="Nombre completo" />
                            {errors.delegado_nombre && <p className="text-red-500 text-xs mt-0.5">{errors.delegado_nombre}</p>}
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Email del delegado *</label>
                            <input type="email" value={data.delegado_email} onChange={e => setData('delegado_email', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                                placeholder="correo@ejemplo.com" />
                            {errors.delegado_email && <p className="text-red-500 text-xs mt-0.5">{errors.delegado_email}</p>}
                        </div>
                    </div>
                    <div className="grid grid-cols-3 gap-3">
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Teléfono</label>
                            <input type="text" value={data.delegado_telefono} onChange={e => setData('delegado_telefono', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Opcional" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Documento</label>
                            <input type="text" value={data.delegado_documento} onChange={e => setData('delegado_documento', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Opcional" />
                        </div>
                        <div>
                            <label className="text-xs text-gray-500 block mb-1">Empresa</label>
                            <input type="text" value={data.delegado_empresa} onChange={e => setData('delegado_empresa', e.target.value)}
                                className="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" placeholder="Opcional" />
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-1">
                        <button type="button" onClick={() => { setShowCrear(false); reset() }}
                            className="text-sm text-gray-500 hover:text-gray-700 px-3 py-1.5">Cancelar</button>
                        <button type="submit" disabled={processing}
                            className="text-sm bg-blue-600 text-white px-4 py-1.5 rounded hover:bg-blue-700 disabled:opacity-50 transition">
                            {processing ? 'Guardando…' : 'Guardar y enviar invitación'}
                        </button>
                    </div>
                </form>
            )}

            {/* Lista */}
            <div className="bg-white rounded-lg shadow p-4">
                {listaTab.length === 0 ? (
                    <p className="text-sm text-gray-400 py-6 text-center">
                        No hay poderes con estado "{tab}".
                    </p>
                ) : (
                    listaTab.map(poder => (
                        <PoderRow
                            key={poder.id}
                            poder={poder}
                            reunionId={reunion.id}
                            onAprobar={handleAprobar}
                            onRechazar={handleRechazar}
                            onRevocar={handleRevocar}
                        />
                    ))
                )}
            </div>
        </AdminLayout>
    )
}
