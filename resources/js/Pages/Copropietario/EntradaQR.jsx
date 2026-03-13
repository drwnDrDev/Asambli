import { Head, useForm } from '@inertiajs/react'
import GuestLayout from '@/Layouts/GuestLayout'

export default function EntradaQR({ reunion, token }) {
    const { data, setData, post, processing, errors } = useForm({
        numero_documento: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post(route('quick-access.qr.store', token))
    }

    return (
        <GuestLayout>
            <Head title="Acceso a la Reunión" />

            {/* Header con icono y título */}
            <div className="text-center mb-6">
                <div className="w-12 h-12 bg-brand/20 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="text-brand"
                    >
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
                        <circle cx="9" cy="7" r="4" />
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
                        <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                    </svg>
                </div>
                <h2 className="text-xl font-bold text-sidebar-text-active">
                    {reunion.titulo}
                </h2>
                <p className="text-[13px] text-sidebar-text mt-1">
                    Ingresa tu número de cédula para acceder
                </p>
            </div>

            {/* Formulario */}
            <form onSubmit={submit}>
                <div>
                    <label className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        Número de cédula
                    </label>
                    <input
                        type="text"
                        inputMode="numeric"
                        autoFocus
                        placeholder="Ej: 1234567890"
                        value={data.numero_documento}
                        onChange={(e) => setData('numero_documento', e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                    {errors.numero_documento && (
                        <p className="mt-1.5 text-[12px] text-danger">
                            {errors.numero_documento}
                        </p>
                    )}
                </div>

                <button
                    type="submit"
                    disabled={processing}
                    className="w-full mt-4 px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                >
                    {processing ? 'Verificando...' : 'Entrar a la reunión'}
                </button>
            </form>
        </GuestLayout>
    )
}
