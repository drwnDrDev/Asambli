import GuestLayout from '@/Layouts/GuestLayout'
import { Head, Link, useForm } from '@inertiajs/react'

export default function AccesoRapido() {
    const { data, setData, post, processing, errors, reset } = useForm({
        tipo_documento: 'CC',
        numero_documento: '',
        pin: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post(route('quick-access.pin.store'), { onFinish: () => reset('pin') })
    }

    const tiposDocumento = [
        { value: 'CC', label: 'CC - Cédula de Ciudadanía' },
        { value: 'CE', label: 'CE - Cédula de Extranjería' },
        { value: 'NIT', label: 'NIT' },
        { value: 'PP', label: 'PP - Pasaporte' },
        { value: 'TI', label: 'TI - Tarjeta de Identidad' },
        { value: 'PEP', label: 'PEP' },
    ]

    return (
        <GuestLayout>
            <Head title="Acceso con PIN" />

            <h2 className="text-xl font-bold text-sidebar-text-active tracking-tight mb-1">
                Acceso con PIN
            </h2>
            <p className="text-[13px] text-sidebar-text mb-6">
                Ingresa tu tipo y número de documento junto con el PIN que te proporcionó el administrador.
            </p>

            <form onSubmit={submit} className="space-y-4">
                {/* Tipo de documento */}
                <div>
                    <label htmlFor="tipo_documento" className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        Tipo de documento
                    </label>
                    <select
                        id="tipo_documento"
                        name="tipo_documento"
                        value={data.tipo_documento}
                        onChange={e => setData('tipo_documento', e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    >
                        {tiposDocumento.map(tipo => (
                            <option key={tipo.value} value={tipo.value}>
                                {tipo.label}
                            </option>
                        ))}
                    </select>
                    {errors.tipo_documento && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.tipo_documento}</p>
                    )}
                </div>

                {/* Número de documento */}
                <div>
                    <label htmlFor="numero_documento" className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        Número de documento
                    </label>
                    <input
                        id="numero_documento"
                        type="text"
                        inputMode="numeric"
                        name="numero_documento"
                        value={data.numero_documento}
                        onChange={e => setData('numero_documento', e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                    {errors.numero_documento && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.numero_documento}</p>
                    )}
                </div>

                {/* PIN */}
                <div>
                    <label htmlFor="pin" className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        PIN (6 dígitos)
                    </label>
                    <input
                        id="pin"
                        type="text"
                        inputMode="numeric"
                        name="pin"
                        value={data.pin}
                        maxLength={6}
                        onChange={e => setData('pin', e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                    {errors.pin && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.pin}</p>
                    )}
                </div>

                {/* Submit button */}
                <button
                    type="submit"
                    disabled={processing}
                    className="w-full px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    {processing ? 'Verificando...' : 'Ingresar'}
                </button>
            </form>

            {/* Link de regreso */}
            <div className="mt-5 text-center">
                <Link
                    href={route('login')}
                    className="text-[13px] text-brand hover:text-brand-dark transition-colors"
                >
                    ← Volver al inicio de sesión
                </Link>
            </div>
        </GuestLayout>
    )
}
