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
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800 space-y-3">
                    <p className="font-semibold">Formato del archivo (CSV o Excel)</p>

                    <div>
                        <p className="text-xs font-semibold text-blue-700 mb-1">Campos obligatorios:</p>
                        <ul className="text-xs text-blue-800 space-y-1 ml-2">
                            <li><span className="font-mono font-bold">numero</span> — número de la unidad (ej: 101)</li>
                            <li><span className="font-mono font-bold">email</span> — correo del copropietario</li>
                            <li><span className="font-mono font-bold">coeficiente</span> — coeficiente de copropiedad (ej: 3.4500)</li>
                            <li><span className="font-mono font-bold">tipo_documento</span> — CC, CE, NIT, PP, TI o PEP</li>
                            <li><span className="font-mono font-bold">numero_documento</span> — número del documento de identidad</li>
                        </ul>
                    </div>

                    <div>
                        <p className="text-xs font-semibold text-blue-700 mb-1">Campos opcionales:</p>
                        <ul className="text-xs text-blue-800 space-y-1 ml-2">
                            <li><span className="font-mono">nombre</span> — nombre completo (si no va, se usa el email)</li>
                            <li><span className="font-mono">torre</span> — torre o bloque (ej: A, B, 1). <strong>Requerido si el conjunto tiene varias torres con numeración repetida.</strong></li>
                            <li><span className="font-mono">tipo</span> — apartamento, local, parqueadero u otro (default: apartamento)</li>
                            <li><span className="font-mono">piso</span> — piso de la unidad</li>
                            <li><span className="font-mono">telefono</span> — teléfono de contacto</li>
                            <li><span className="font-mono">es_residente</span> — true/false (default: true)</li>
                        </ul>
                    </div>

                    <div>
                        <p className="text-xs font-semibold text-blue-700 mb-1">Ejemplo con torres:</p>
                        <code className="block bg-blue-100 rounded p-2 text-xs font-mono whitespace-pre">
{`numero,torre,nombre,email,tipo_documento,numero_documento,coeficiente
101,A,Juan Pérez,juan@ejemplo.com,CC,12345678,3.4500
101,B,María García,maria@ejemplo.com,CC,87654321,3.4500
102,A,Carlos López,carlos@ejemplo.com,CC,11223344,2.8000`}
                        </code>
                    </div>

                    <p className="text-xs text-blue-600 border-t border-blue-200 pt-2">
                        La suma de todos los coeficientes debe ser ≤ 100. La combinación <strong>numero + torre</strong> identifica de forma única cada unidad dentro del conjunto.
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
