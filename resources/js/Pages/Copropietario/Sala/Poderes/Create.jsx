import { useForm } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'
import { Link } from '@inertiajs/react'

export default function Create({ reunion }) {
    const { data, setData, post, processing, errors } = useForm({
        delegado_nombre:   '',
        delegado_email:    '',
        delegado_telefono: '',
        delegado_documento:'',
        delegado_empresa:  '',
    })

    const submit = (e) => {
        e.preventDefault()
        post(`/sala/${reunion.id}/poderes`)
    }

    return (
        <SalaLayout>
            <div className="max-w-md mx-auto">
                <Link href="/sala" className="text-sm text-slate-400 hover:text-slate-300 mb-4 inline-block">
                    ← Volver
                </Link>
                <h1 className="text-xl font-bold mb-1">Registrar poder</h1>
                <p className="text-slate-400 text-sm mb-6">
                    Reunión: <span className="text-white">{reunion.titulo}</span>
                </p>

                <div className="bg-slate-800 rounded-xl p-4 mb-6 text-sm text-slate-300">
                    Al registrar un poder, autorizas a la persona indicada a votar en tu nombre.
                    Tu acceso a la sala quedará bloqueado una vez que el administrador apruebe el poder.
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-xs text-slate-400 mb-1">Nombre completo *</label>
                        <input
                            type="text"
                            value={data.delegado_nombre}
                            onChange={e => setData('delegado_nombre', e.target.value)}
                            className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="Nombre del delegado"
                        />
                        {errors.delegado_nombre && <p className="text-red-400 text-xs mt-1">{errors.delegado_nombre}</p>}
                    </div>

                    <div>
                        <label className="block text-xs text-slate-400 mb-1">Correo electrónico *</label>
                        <input
                            type="email"
                            value={data.delegado_email}
                            onChange={e => setData('delegado_email', e.target.value)}
                            className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="correo@ejemplo.com"
                        />
                        {errors.delegado_email && <p className="text-red-400 text-xs mt-1">{errors.delegado_email}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-slate-400 mb-1">Teléfono</label>
                            <input
                                type="text"
                                value={data.delegado_telefono}
                                onChange={e => setData('delegado_telefono', e.target.value)}
                                className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="Opcional"
                            />
                        </div>
                        <div>
                            <label className="block text-xs text-slate-400 mb-1">Documento</label>
                            <input
                                type="text"
                                value={data.delegado_documento}
                                onChange={e => setData('delegado_documento', e.target.value)}
                                className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                                placeholder="Opcional"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="block text-xs text-slate-400 mb-1">Empresa / Inmobiliaria</label>
                        <input
                            type="text"
                            value={data.delegado_empresa}
                            onChange={e => setData('delegado_empresa', e.target.value)}
                            className="w-full bg-slate-800 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:ring-2 focus:ring-amber-500"
                            placeholder="Solo si aplica"
                        />
                    </div>

                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold py-2.5 rounded-xl text-sm transition disabled:opacity-50"
                    >
                        {processing ? 'Enviando…' : 'Enviar solicitud de poder'}
                    </button>
                </form>
            </div>
        </SalaLayout>
    )
}
