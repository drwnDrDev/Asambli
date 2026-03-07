import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, usePage } from '@inertiajs/react'
import { useState } from 'react'

export default function Index({ resumen }) {
    const { flash, errors: pageErrors } = usePage().props
    const { data, setData, post, processing, errors } = useForm({ archivo: null })
    const [confirmando, setConfirmando] = useState(false)

    const handleSubmit = (e) => {
        e.preventDefault()
        if (resumen && !confirmando) {
            setConfirmando(true)
            return
        }
        setConfirmando(false)
        post('/admin/padron/import', { forceFormData: true })
    }

    const cancelarConfirmacion = () => setConfirmando(false)

    const formatFecha = (iso) => {
        if (!iso) return '—'
        return new Date(iso).toLocaleString('es-CO', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        })
    }

    return (
        <AdminLayout title="Importar Copropietarios">
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            <div className="max-w-xl space-y-6">

                {/* Resumen de datos existentes */}
                {resumen && (
                    <div className="bg-surface border border-surface-border rounded-xl p-5 space-y-3">
                        <p className="text-sm font-semibold text-app-text-primary">Padrón actual</p>
                        <div className="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p className="text-2xl font-bold text-brand">{resumen.copropietarios}</p>
                                <p className="text-xs text-app-text-muted mt-0.5">Copropietarios</p>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-brand">{resumen.unidades}</p>
                                <p className="text-xs text-app-text-muted mt-0.5">Unidades</p>
                            </div>
                            <div>
                                <p className="text-2xl font-bold text-brand">{resumen.totalCoeficiente}%</p>
                                <p className="text-xs text-app-text-muted mt-0.5">Coeficiente total</p>
                            </div>
                        </div>
                        <p className="text-xs text-app-text-muted text-center border-t border-surface-border pt-3">
                            Última importación: <span className="font-medium text-app-text-secondary">{formatFecha(resumen.ultimaImportacion)}</span>
                        </p>
                    </div>
                )}

                {/* Instrucciones */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                    <p className="font-semibold mb-2">Formato del archivo (CSV o Excel):</p>
                    <code className="block bg-blue-100 rounded p-2 text-xs font-mono">
                        numero,nombre,email,coeficiente<br/>
                        101,Juan Pérez,juan@ejemplo.com,3.45<br/>
                        102,María García,maria@ejemplo.com,2.80
                    </code>
                    <p className="mt-2 text-xs text-blue-600">
                        Campos requeridos: numero, email, coeficiente. La suma de coeficientes debe ser ≤ 100.
                        Columnas opcionales: nombre, tipo, torre, piso, es_residente, telefono.
                    </p>
                </div>

                {/* Formulario */}
                <form onSubmit={handleSubmit} className="bg-white rounded-lg shadow p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Archivo CSV o Excel
                        </label>
                        <input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            onChange={e => { setData('archivo', e.target.files[0]); setConfirmando(false) }}
                            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                        {(errors.archivo || pageErrors?.archivo) && (
                            <p className="text-red-500 text-xs mt-1">{errors.archivo || pageErrors?.archivo}</p>
                        )}
                    </div>

                    {/* Advertencia de confirmación */}
                    {confirmando && (
                        <div className="bg-amber-50 border border-amber-300 rounded-lg p-4 space-y-3">
                            <p className="text-sm font-semibold text-amber-800">¿Confirmar reimportación?</p>
                            <p className="text-xs text-amber-700">
                                Ya existe un padrón con <strong>{resumen.copropietarios} copropietarios</strong> y <strong>{resumen.unidades} unidades</strong>.
                                La importación actualizará los registros existentes y agregará los nuevos. No se eliminarán datos que no estén en el archivo.
                            </p>
                            <div className="flex gap-3">
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="bg-amber-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-amber-700 disabled:opacity-50 transition"
                                >
                                    {processing ? 'Importando...' : 'Sí, reimportar'}
                                </button>
                                <button
                                    type="button"
                                    onClick={cancelarConfirmacion}
                                    className="px-4 py-2 rounded-lg text-sm font-medium text-gray-600 hover:text-gray-800 transition"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    )}

                    {!confirmando && (
                        <button
                            type="submit"
                            disabled={processing || !data.archivo}
                            className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition"
                        >
                            {processing ? 'Importando...' : resumen ? 'Reimportar padrón' : 'Importar padrón'}
                        </button>
                    )}
                </form>
            </div>
        </AdminLayout>
    )
}
