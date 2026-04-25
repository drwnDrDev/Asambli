import GuestLayout from '@/Layouts/GuestLayout'
import { Head, Link, useForm } from '@inertiajs/react'

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    })

    const submit = (e) => {
        e.preventDefault()
        post(route('login'), { onFinish: () => reset('password') })
    }

    return (
        <GuestLayout>
            <Head title="Iniciar sesión" />

            <h2 className="text-xl font-bold text-sidebar-text-active tracking-tight mb-1">
                Bienvenido de nuevo
            </h2>
            <p className="text-[13px] text-sidebar-text mb-6">
                Ingresa a tu cuenta de ASAMBLI
            </p>

            {status && (
                <div className="mb-4 px-3.5 py-2.5 rounded bg-success-bg border border-success text-success text-[13px]">
                    {status}
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                {/* Email */}
                <div>
                    <label htmlFor="email" className="block text-[13px] font-medium text-sidebar-text mb-1.5">
                        Correo electrónico
                    </label>
                    <input
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        autoComplete="username"
                        autoFocus
                        onChange={e => setData('email', e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                    {errors.email && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.email}</p>
                    )}
                </div>

                {/* Password */}
                <div>
                    <div className="flex items-center justify-between mb-1.5">
                        <label htmlFor="password" className="text-[13px] font-medium text-sidebar-text">
                            Contraseña
                        </label>
                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-[12px] text-brand hover:text-brand-dark transition-colors"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                    </div>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        autoComplete="current-password"
                        onChange={e => setData('password', e.target.value)}
                        className="w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors"
                    />
                    {errors.password && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.password}</p>
                    )}
                </div>

                {/* Remember + Submit */}
                <div className="flex items-center justify-between pt-1">
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="checkbox"
                            name="remember"
                            checked={data.remember}
                            onChange={e => setData('remember', e.target.checked)}
                            className="w-3.5 h-3.5 accent-brand cursor-pointer"
                        />
                        <span className="text-[13px] text-sidebar-text">Recordarme</span>
                    </label>

                    <button
                        type="submit"
                        disabled={processing}
                        className="px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Entrando...' : 'Iniciar sesión'}
                    </button>
                </div>
            </form>

        </GuestLayout>
    )
}
