import AdminLayout from '@/Layouts/AdminLayout'
import { useForm, usePage } from '@inertiajs/react'

export default function Index() {
    const { flash, errors: pageErrors } = usePage().props
    const { data, setData, post, processing, errors } = useForm({ archivo: null })

    const submit = (e) => {
        e.preventDefault()
        post('/admin/padron/import', { forceFormData: true })
    }

    return (
        <AdminLayout title="Padrón de Copropietarios">
            {flash?.success && (
                <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                    {flash.success}
                </div>
            )}

            <div className="max-w-xl space-y-6">
                {/* Instrucciones */}
                <div className="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
                    <p className="font-semibold mb-2">Formato del CSV:</p>
                    <code className="block bg-blue-100 rounded p-2 text-xs font-mono">
                        numero_unidad,nombre,email,coeficiente<br/>
                        101,Juan Pérez,juan@ejemplo.com,3.45<br/>
                        102,María García,maria@ejemplo.com,2.80
                    </code>
                    <p className="mt-2 text-xs text-blue-600">
                        La suma de coeficientes debe ser ≤ 100. Se hace upsert por email.
                    </p>
                </div>

                {/* Formulario */}
                <form onSubmit={submit} className="bg-white rounded-lg shadow p-6 space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-gray-700 mb-2">
                            Archivo CSV
                        </label>
                        <input
                            type="file"
                            accept=".csv,.txt"
                            onChange={e => setData('archivo', e.target.files[0])}
                            className="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                        />
                        {(errors.archivo || pageErrors?.archivo) && (
                            <p className="text-red-500 text-xs mt-1">{errors.archivo || pageErrors?.archivo}</p>
                        )}
                    </div>

                    <button
                        type="submit"
                        disabled={processing || !data.archivo}
                        className="bg-blue-600 text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 transition"
                    >
                        {processing ? 'Importando...' : 'Importar padrón'}
                    </button>
                </form>
            </div>
        </AdminLayout>
    )
}
