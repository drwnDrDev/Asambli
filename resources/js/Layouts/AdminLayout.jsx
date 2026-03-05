import { Link, usePage, router } from '@inertiajs/react'

export default function AdminLayout({ children, title }) {
    const { auth } = usePage().props

    const logout = () => router.post('/logout')

    return (
        <div className="min-h-screen bg-gray-100">
            <nav className="bg-white shadow-sm border-b border-gray-200">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex justify-between h-16 items-center">
                        <div className="flex items-center gap-6">
                            <span className="font-bold text-blue-700 text-lg tracking-tight">ASAMBLI</span>
                            <div className="hidden sm:flex items-center gap-4">
                                <Link href="/admin/dashboard" className="text-sm text-gray-600 hover:text-blue-700 transition">
                                    Dashboard
                                </Link>
                                <Link href="/admin/padron" className="text-sm text-gray-600 hover:text-blue-700 transition">
                                    Padrón
                                </Link>
                                <Link href="/admin/reuniones" className="text-sm text-gray-600 hover:text-blue-700 transition">
                                    Reuniones
                                </Link>
                            </div>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-sm text-gray-500">{auth?.user?.name}</span>
                            <button
                                onClick={logout}
                                className="text-xs text-gray-400 hover:text-red-600 transition"
                            >
                                Salir
                            </button>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {title && (
                    <h1 className="text-2xl font-bold text-gray-900 mb-6">{title}</h1>
                )}
                {children}
            </main>
        </div>
    )
}
