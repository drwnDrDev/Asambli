import { router, usePage } from '@inertiajs/react'
import SalaLayout from '@/Layouts/SalaLayout'
import { Link } from '@inertiajs/react'

const ESTADO_COLOR = {
    pendiente: 'text-yellow-400',
    aprobado:  'text-emerald-400',
    rechazado: 'text-red-400',
    revocado:  'text-slate-400',
    expirado:  'text-slate-500',
}

const ESTADO_LABEL = {
    pendiente: 'Pendiente de aprobación',
    aprobado:  'Aprobado — activo',
    rechazado: 'Rechazado',
    revocado:  'Revocado',
    expirado:  'Expirado',
}

export default function MisPoderes({ miPoder = null }) {
    const { flash } = usePage().props

    const handleRetirar = (poderId) => {
        if (!confirm('¿Retirar la solicitud de poder?')) return
        router.delete(`/sala/poderes/${poderId}`, { preserveScroll: true })
    }

    return (
        <SalaLayout>
            <div className="max-w-md mx-auto">
                <div className="flex items-center justify-between mb-6">
                    <h1 className="text-xl font-bold">Mi poder</h1>
                    <Link href="/sala" className="text-sm text-slate-400 hover:text-slate-200 transition">
                        ← Volver
                    </Link>
                </div>

                {flash?.success && (
                    <div className="mb-4 bg-emerald-900/40 border border-emerald-700 text-emerald-300 px-4 py-3 rounded-lg text-sm">
                        {flash.success}
                    </div>
                )}

                {miPoder ? (
                    <div className="bg-slate-800 rounded-xl p-5">
                        <div className="flex items-start justify-between mb-4">
                            <div>
                                <p className="text-xs text-slate-400 mb-1">Estado</p>
                                <p className={`text-sm font-semibold ${ESTADO_COLOR[miPoder.estado]}`}>
                                    {ESTADO_LABEL[miPoder.estado]}
                                </p>
                            </div>
                        </div>

                        <div className="border-t border-slate-700 pt-4">
                            <p className="text-xs text-slate-400 mb-1">Delegado a</p>
                            <p className="font-semibold text-white">{miPoder.apoderado?.user?.name}</p>
                            {miPoder.apoderado?.empresa && (
                                <p className="text-xs text-slate-400 mt-0.5">{miPoder.apoderado.empresa}</p>
                            )}
                        </div>

                        {miPoder.estado === 'aprobado' && (
                            <div className="mt-4 bg-amber-900/30 border border-amber-700/50 rounded-lg px-4 py-3 text-sm text-amber-300">
                                Tu delegado está autorizado para votar en tu nombre. No puedes votar directamente mientras el poder esté activo.
                            </div>
                        )}

                        {miPoder.estado === 'pendiente' && (
                            <div className="mt-4 flex justify-end">
                                <button
                                    onClick={() => handleRetirar(miPoder.id)}
                                    className="text-xs text-red-400 hover:text-red-300 transition"
                                >
                                    Retirar solicitud
                                </button>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="text-center py-12">
                        <div className="text-4xl mb-4">📋</div>
                        <p className="text-slate-400 text-sm mb-6">
                            No tienes ningún poder activo. Puedes delegar tu voto antes de la reunión.
                        </p>
                        <Link
                            href="/sala/poderes/crear"
                            className="inline-block bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold px-6 py-2.5 rounded-xl text-sm transition"
                        >
                            Registrar poder
                        </Link>
                    </div>
                )}
            </div>
        </SalaLayout>
    )
}
