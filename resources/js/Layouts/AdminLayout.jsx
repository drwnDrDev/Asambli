import { useState } from 'react'
import { Link, usePage, router } from '@inertiajs/react'

const logout = () => router.post('/logout')

const NAV_ADMIN = [
    {
        href: '/admin/dashboard',
        label: 'Dashboard',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                <rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>
            </svg>
        ),
    },
    {
        href: '/admin/reuniones',
        label: 'Reuniones',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        ),
    },
    {
        href: '/admin/copropietarios',
        label: 'Copropietarios',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        ),
    },
    {
        href: '/admin/padron',
        label: 'Importar',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        ),
    },
]

const NAV_SUPERADMIN = [
    {
        href: '/super-admin/tenants',
        label: 'Conjuntos',
        icon: (
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
        ),
    },
]

function NavItem({ href, label, icon, onClick }) {
    const { url } = usePage()
    const isActive = url === href || url.startsWith(href + '/')

    return (
        <Link
            href={href}
            onClick={onClick}
            className={[
                'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 mb-0.5',
                'border-l-[3px]',
                isActive
                    ? 'bg-sidebar-active text-sidebar-text-active border-brand'
                    : 'text-sidebar-text border-transparent hover:bg-sidebar-hover hover:text-sidebar-text-active',
            ].join(' ')}
        >
            <span className={isActive ? 'opacity-100' : 'opacity-60'}>{icon}</span>
            {label}
        </Link>
    )
}

function SidebarContent({ navItems, user, onClose }) {
    return (
        <div className="w-64 bg-sidebar-bg border-r border-sidebar-border flex flex-col h-screen sticky top-0 flex-shrink-0">
            {/* Logo */}
            <div className="px-5 py-6 border-b border-sidebar-border">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2.5">
                        <div className="w-8 h-8 bg-brand rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <span className="font-bold text-[17px] text-sidebar-text-active tracking-tight">
                            ASAMBLI
                        </span>
                    </div>
                    {/* Botón cerrar — solo visible cuando es drawer (onClose existe) */}
                    {onClose && (
                        <button
                            onClick={onClose}
                            className="lg:hidden p-1.5 text-sidebar-text hover:text-sidebar-text-active rounded transition-colors"
                            aria-label="Cerrar menú"
                        >
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    )}
                </div>
            </div>

            {/* Nav */}
            <nav className="flex-1 px-3 py-4 overflow-y-auto">
                {navItems.map(item => (
                    <NavItem key={item.href} {...item} onClick={onClose} />
                ))}
            </nav>

            {/* User footer */}
            <div className="px-4 py-4 border-t border-sidebar-border">
                <div className="flex items-center gap-2.5 mb-3">
                    <div className="w-8 h-8 rounded-full bg-sidebar-active border border-sidebar-border flex items-center justify-center flex-shrink-0 text-sidebar-text-active font-semibold text-sm">
                        {user?.name?.charAt(0)?.toUpperCase() ?? '?'}
                    </div>
                    <div className="min-w-0">
                        <p className="text-sidebar-text-active text-[13px] font-semibold truncate">
                            {user?.name}
                        </p>
                        <p className="text-sidebar-text text-[11px] capitalize">
                            {user?.rol ?? 'Usuario'}
                        </p>
                    </div>
                </div>
                <button
                    onClick={logout}
                    className="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 rounded text-[12px] font-medium text-sidebar-text border border-sidebar-border bg-transparent hover:bg-danger-bg hover:border-danger hover:text-danger transition-all duration-200"
                >
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                    Cerrar sesión
                </button>
            </div>
        </div>
    )
}

export default function AdminLayout({ children, title }) {
    const { auth } = usePage().props
    const [drawerOpen, setDrawerOpen] = useState(false)

    const isSuperAdmin = auth?.user?.rol === 'super_admin'
    const navItems = isSuperAdmin ? NAV_SUPERADMIN : NAV_ADMIN

    return (
        <div className="flex min-h-screen bg-content-bg font-sans">

            {/* Sidebar — solo desktop */}
            <div className="hidden lg:flex">
                <SidebarContent navItems={navItems} user={auth?.user} />
            </div>

            {/* Mobile overlay */}
            {drawerOpen && (
                <div
                    onClick={() => setDrawerOpen(false)}
                    className="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"
                />
            )}

            {/* Mobile drawer */}
            <div
                className="fixed inset-y-0 left-0 z-50 lg:hidden transition-transform duration-300 ease-in-out"
                style={{ transform: drawerOpen ? 'translateX(0)' : 'translateX(-100%)' }}
                aria-hidden={!drawerOpen}
                inert={!drawerOpen ? '' : undefined}
            >
                <SidebarContent
                    navItems={navItems}
                    user={auth?.user}
                    onClose={() => setDrawerOpen(false)}
                />
            </div>

            {/* Main content */}
            <div className="flex-1 flex flex-col min-w-0">
                {/* Topbar */}
                <header className="h-14 bg-surface border-b border-surface-border flex items-center px-6 gap-4 sticky top-0 z-30 shadow-sm">
                    {/* Hamburger — solo mobile */}
                    <button
                        onClick={() => setDrawerOpen(true)}
                        className="lg:hidden p-1.5 text-app-text-secondary hover:text-app-text-primary rounded transition-colors"
                        aria-label="Abrir menú"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="3" y1="12" x2="21" y2="12"/>
                            <line x1="3" y1="6" x2="21" y2="6"/>
                            <line x1="3" y1="18" x2="21" y2="18"/>
                        </svg>
                    </button>

                    {title && (
                        <h1 className="text-[15px] font-semibold text-app-text-primary flex-1">
                            {title}
                        </h1>
                    )}
                    {!title && <div className="flex-1" />}
                </header>

                {/* Page content */}
                <main className="flex-1 p-7">
                    {children}
                </main>
            </div>
        </div>
    )
}
