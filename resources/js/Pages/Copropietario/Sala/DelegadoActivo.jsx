import SalaLayout from '@/Layouts/SalaLayout'
import { Link } from '@inertiajs/react'

export default function DelegadoActivo({ delegadoNombre, delegadoEmpresa }) {
    return (
        <SalaLayout>
            <div className="min-h-[60vh] flex items-center justify-center">
                <div className="text-center max-w-sm">
                    <div className="text-5xl mb-6">📋</div>
                    <h2 className="text-xl font-semibold mb-3">Has delegado tu voto</h2>
                    <p className="text-slate-400 text-sm mb-2">
                        Otorgaste poder a:
                    </p>
                    <div className="bg-slate-800 rounded-xl px-5 py-4 mb-6">
                        <p className="font-semibold text-white">{delegadoNombre}</p>
                        {delegadoEmpresa && (
                            <p className="text-xs text-slate-400 mt-1">{delegadoEmpresa}</p>
                        )}
                    </div>
                    <p className="text-slate-400 text-sm mb-6">
                        Tu representante está autorizado para votar en tu nombre.
                        No puedes acceder a la sala mientras el poder esté activo.
                    </p>
                    <p className="text-slate-500 text-xs mb-6">
                        Si tienes dudas, comunícate con el administrador del conjunto.
                    </p>
                    <Link
                        href="/sala"
                        className="inline-block text-sm text-slate-400 hover:text-slate-200 transition"
                    >
                        ← Volver a mis reuniones
                    </Link>
                </div>
            </div>
        </SalaLayout>
    )
}
