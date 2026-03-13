import { Head, useForm } from '@inertiajs/react'
import GuestLayout from '@/Layouts/GuestLayout'

const TIPO_DOCUMENTO_OPTIONS = [
    { value: 'CC',  label: 'CC — Cédula de Ciudadanía' },
    { value: 'CE',  label: 'CE — Cédula de Extranjería' },
    { value: 'NIT', label: 'NIT' },
    { value: 'PP',  label: 'PP — Pasaporte' },
    { value: 'TI',  label: 'TI — Tarjeta de Identidad' },
    { value: 'PEP', label: 'PEP — Permiso Especial de Permanencia' },
]

function unidadLabel(u) {
    const tipo  = u.tipo ? u.tipo.charAt(0).toUpperCase() + u.tipo.slice(1).toLowerCase() : 'Unidad'
    const torre = u.torre ? ` · Torre ${u.torre}` : ''
    const coef  = u.coeficiente != null ? ` · ${u.coeficiente}%` : ''
    return `${tipo} ${u.numero}${torre}${coef}`
}

export default function OnboardingIndex({ token, user, unidades }) {
    const { data, setData, post, processing, errors } = useForm({
        nombre:               user.name              || '',
        tipo_documento:       user.tipo_documento    || '',
        numero_documento:     user.numero_documento  || '',
        telefono:             user.telefono          || '',
        password:             '',
        password_confirmation: '',
    })

    function handleSubmit(e) {
        e.preventDefault()
        post(route('onboarding.store', token))
    }

    const inputClass =
        'w-full px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.6)] text-sidebar-text-active text-sm placeholder-sidebar-text focus:outline-none focus:ring-2 focus:ring-brand focus:border-brand transition-colors'
    const labelClass = 'block text-[13px] font-medium text-sidebar-text mb-1.5'

    return (
        <GuestLayout>
            <Head title="Configura tu acceso" />

            {/* Header */}
            <div className="mb-6">
                <h2 className="text-xl font-bold text-sidebar-text-active leading-tight">
                    Bienvenido a ASAMBLI
                </h2>
                <p className="mt-1.5 text-sm text-sidebar-text">
                    Completa tu perfil y crea tu contraseña para acceder al sistema.
                </p>
            </div>

            {/* Unidades */}
            <div className="mb-6">
                <p className="text-[11px] font-semibold uppercase tracking-widest text-sidebar-text mb-2">
                    Tu(s) unidad(es)
                </p>
                {unidades && unidades.length > 0 ? (
                    <ul className="space-y-1.5">
                        {unidades.map((u) => (
                            <li
                                key={u.id}
                                className="flex items-center gap-2 text-sm text-sidebar-text-active bg-[rgba(12,17,29,0.4)] border border-sidebar-border rounded px-3 py-2"
                            >
                                <span className="w-1.5 h-1.5 rounded-full bg-brand flex-shrink-0" />
                                {unidadLabel(u)}
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p className="text-sm text-sidebar-text italic">
                        Sin unidades asignadas aún.
                    </p>
                )}
            </div>

            <div className="border-t border-sidebar-border mb-6" />

            <form onSubmit={handleSubmit} className="space-y-4" noValidate>

                {/* Email (readonly) */}
                <div>
                    <p className={labelClass}>Correo electrónico</p>
                    <p className="text-sm text-sidebar-text-active px-3.5 py-2.5 rounded border border-sidebar-border bg-[rgba(12,17,29,0.3)] truncate">
                        {user.email}
                    </p>
                </div>

                {/* Nombre */}
                <div>
                    <label htmlFor="nombre" className={labelClass}>
                        Nombre completo
                    </label>
                    <input
                        id="nombre"
                        type="text"
                        autoComplete="name"
                        value={data.nombre}
                        onChange={(e) => setData('nombre', e.target.value)}
                        className={inputClass}
                        placeholder="Tu nombre completo"
                    />
                    {errors.nombre && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.nombre}</p>
                    )}
                </div>

                {/* Tipo documento */}
                <div>
                    <label htmlFor="tipo_documento" className={labelClass}>
                        Tipo de documento
                    </label>
                    <select
                        id="tipo_documento"
                        value={data.tipo_documento}
                        onChange={(e) => setData('tipo_documento', e.target.value)}
                        className={inputClass}
                    >
                        <option value="" disabled>Selecciona un tipo…</option>
                        {TIPO_DOCUMENTO_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                    {errors.tipo_documento && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.tipo_documento}</p>
                    )}
                </div>

                {/* Número documento */}
                <div>
                    <label htmlFor="numero_documento" className={labelClass}>
                        Número de documento
                    </label>
                    <input
                        id="numero_documento"
                        type="text"
                        value={data.numero_documento}
                        onChange={(e) => setData('numero_documento', e.target.value)}
                        className={inputClass}
                        placeholder="Ej. 1234567890"
                    />
                    {errors.numero_documento && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.numero_documento}</p>
                    )}
                </div>

                {/* Teléfono */}
                <div>
                    <label htmlFor="telefono" className={labelClass}>
                        Teléfono
                    </label>
                    <input
                        id="telefono"
                        type="tel"
                        value={data.telefono}
                        onChange={(e) => setData('telefono', e.target.value)}
                        className={inputClass}
                        placeholder="Ej. 3001234567"
                    />
                    {errors.telefono && (
                        <p className="mt-1.5 text-[12px] text-danger">{errors.telefono}</p>
                    )}
                </div>

                <div className="border-t border-sidebar-border pt-4">
                    <p className="text-[11px] font-semibold uppercase tracking-widest text-sidebar-text mb-4">
                        Crea tu contraseña
                    </p>

                    {/* Password */}
                    <div className="mb-4">
                        <label htmlFor="password" className={labelClass}>
                            Contraseña
                        </label>
                        <input
                            id="password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className={inputClass}
                            placeholder="Mínimo 8 caracteres"
                        />
                        {errors.password && (
                            <p className="mt-1.5 text-[12px] text-danger">{errors.password}</p>
                        )}
                    </div>

                    {/* Confirm password */}
                    <div>
                        <label htmlFor="password_confirmation" className={labelClass}>
                            Confirmar contraseña
                        </label>
                        <input
                            id="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            className={inputClass}
                            placeholder="Repite tu contraseña"
                        />
                        {errors.password_confirmation && (
                            <p className="mt-1.5 text-[12px] text-danger">{errors.password_confirmation}</p>
                        )}
                    </div>
                </div>

                {/* Submit */}
                <div className="pt-2">
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full px-5 py-2.5 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {processing ? 'Guardando…' : 'Guardar y acceder'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    )
}
