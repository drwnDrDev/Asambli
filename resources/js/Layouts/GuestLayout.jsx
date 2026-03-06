import { Link } from '@inertiajs/react'

export default function GuestLayout({ children }) {
    return (
        <div className="min-h-screen bg-sidebar-bg flex flex-col items-center justify-center px-4 py-6 font-sans relative overflow-hidden">

            {/* Dot grid */}
            <div
                className="absolute inset-0 pointer-events-none opacity-50"
                style={{
                    backgroundImage: 'radial-gradient(circle, #1e2f50 1px, transparent 1px)',
                    backgroundSize: '28px 28px',
                }}
            />

            {/* Glow top */}
            <div
                className="absolute pointer-events-none"
                style={{
                    top: '-120px',
                    left: '50%',
                    transform: 'translateX(-50%)',
                    width: '600px',
                    height: '400px',
                    background: 'radial-gradient(ellipse, rgba(37,99,235,0.15) 0%, transparent 70%)',
                }}
            />

            {/* Logo */}
            <Link href="/" className="no-underline mb-7 relative z-10">
                <div className="flex items-center gap-2.5">
                    <div className="w-9 h-9 bg-brand rounded-lg flex items-center justify-center flex-shrink-0">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <span className="font-bold text-xl text-sidebar-text-active tracking-tight">
                        ASAMBLI
                    </span>
                </div>
            </Link>

            {/* Card */}
            <div
                className="relative z-10 w-full max-w-[420px] rounded-[14px] p-8 border border-sidebar-border backdrop-blur-md"
                style={{
                    background: 'rgba(22, 30, 48, 0.85)',
                    boxShadow: '0 0 0 1px rgba(37,99,235,0.08), 0 10px 40px rgba(0,0,0,0.4)',
                }}
            >
                {children}
            </div>

            {/* Footer */}
            <p className="relative z-10 mt-6 text-xs text-sidebar-text">
                © {new Date().getFullYear()} ASAMBLI · Gestión de Propiedad Horizontal
            </p>
        </div>
    )
}
