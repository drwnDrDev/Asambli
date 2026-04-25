import GuestLayout from '@/Layouts/GuestLayout'
import { Head, useForm } from '@inertiajs/react'

export default function SalaLogin({ reunion }) {
    const { data, setData, post, processing, errors } = useForm({
        numero_documento: '',
        pin: '',
    })

    const submit = (e) => {
        e.preventDefault()
        post(`/sala/login/${reunion.id}`)
    }

    return (
        <GuestLayout>
            <Head title={`Acceso — ${reunion.titulo}`} />

            <p className="text-[13px] font-semibold text-brand uppercase tracking-wide mb-0.5">
                {reunion.tenant.nombre}
            </p>
            <h2 className="text-xl font-bold text-sidebar-text-active tracking-tight mb-1">
                {reunion.titulo}
            </h2>
            {reunion.fecha_programada && (
                <p className="text-[13px] text-sidebar-text mb-6">{reunion.fecha_programada}</p>
            )}

            <form onSubmit={submit} className="space-y-4">
                <div>
                    <label htmlFor="numero_documento" className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        Número de documento
                    </label>
                    <input
                        id="numero_documento"
                        type="text"
                        value={data.numero_documento}
                        onChange={e => setData('numero_documento', e.target.value)}
                        autoFocus
                        autoComplete="off"
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                </div>

                <div>
                    <label htmlFor="pin" className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        PIN de acceso
                    </label>
                    <input
                        id="pin"
                        type="text"
                        inputMode="numeric"
                        maxLength={6}
                        value={data.pin}
                        onChange={e => setData('pin', e.target.value.replace(/\D/g, ''))}
                        autoComplete="off"
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm tracking-widest text-center text-xl placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                    {errors.pin && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.pin}</p>
                    )}
                </div>

                <div className="flex justify-end pt-1">
                    <button
                        type="submit"
                        disabled={processing}
                        className="px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Verificando...' : 'Ingresar a la reunión'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    )
}
