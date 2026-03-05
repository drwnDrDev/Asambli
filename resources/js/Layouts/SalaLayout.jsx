import { usePage, router } from '@inertiajs/react'

export default function SalaLayout({ children }) {
    const { auth } = usePage().props

    return (
        <div className="min-h-screen bg-slate-900 text-white">
            <header className="bg-slate-800 border-b border-slate-700 px-4 py-3 flex justify-between items-center">
                <span className="font-bold text-blue-400 tracking-tight">ASAMBLI</span>
                <div className="flex items-center gap-3">
                    <span className="text-sm text-slate-400">{auth?.user?.name}</span>
                    <button
                        onClick={() => router.post('/logout')}
                        className="text-xs text-slate-500 hover:text-red-400 transition"
                    >
                        Salir
                    </button>
                </div>
            </header>
            <main className="px-4 py-6 max-w-lg mx-auto">
                {children}
            </main>
        </div>
    )
}
