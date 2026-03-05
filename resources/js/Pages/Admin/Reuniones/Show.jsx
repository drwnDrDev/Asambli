import AdminLayout from '@/Layouts/AdminLayout'
import { Link, router, usePage } from '@inertiajs/react'

const ESTADO_BADGE = {
    borrador:   'bg-gray-100 text-gray-700',
    convocada:  'bg-blue-100 text-blue-700',
    en_curso:   'bg-green-100 text-green-700',
    finalizada: 'bg-slate-100 text-slate-500',
}

export default function Show({ reunion, quorum, copropietarios = [] }) {
    const { flash } = usePage().props

    const accion = (url) => router.post(url, {}, { preserveScroll: true })

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
                            <button onClick={() => accion(`/admin/reuniones/${reunion.id}/iniciar`)}
                                className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                                Iniciar
                            </button>
                        </>
                    )}
                    {reunion.estado === 'convocada' && (
                        <button onClick={() => accion(`/admin/reuniones/${reunion.id}/iniciar`)}
                            className="text-sm bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                            Iniciar reunión
                        </button>
                    )}
                    {reunion.estado === 'en_curso' && (
                        <>
                            <Link href={`/admin/reuniones/${reunion.id}/conducir`}
                                className="text-sm bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                                Panel de conducción
                            </Link>
                            <button onClick={() => accion(`/admin/reuniones/${reunion.id}/finalizar`)}
                                className="text-sm bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition">
                                Finalizar
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
                                Exportar CSV
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

            {/* Lista de copropietarios */}
            <div className="bg-white rounded-lg shadow overflow-hidden">
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
                                <td className="px-4 py-3 text-gray-500">{c.unidad?.numero}</td>
                                <td className="px-4 py-3 text-gray-500">{c.unidad?.coeficiente}%</td>
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
        </AdminLayout>
    )
}
