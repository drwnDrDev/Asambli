import { Head, Link } from '@inertiajs/react'

const FEATURES = [
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
        ),
        title: 'Quórum en Tiempo Real',
        desc: 'Seguimiento en vivo del quórum por unidades o coeficiente. WebSockets con Laravel Reverb.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
            </svg>
        ),
        title: 'Votaciones Seguras',
        desc: 'Votaciones ponderadas por coeficiente de propiedad. Resultados instantáneos en pantalla.',
    },
    {
        icon: (
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
            </svg>
        ),
        title: 'Auditoría SHA-256',
        desc: 'Cada votación genera un hash criptográfico. Reportes PDF y CSV para actas legales.',
    },
]

export default function Welcome({ auth }) {
    return (
        <>
            <Head title="ASAMBLI — Gestión de Asambleas" />

            <div className="min-h-screen bg-sidebar-bg font-sans text-sidebar-text-active relative overflow-hidden">

                {/* Animated glow background */}
                <div
                    className="absolute inset-0 pointer-events-none"
                    style={{
                        background: 'radial-gradient(ellipse 80% 60% at 50% -10%, rgba(37,99,235,0.18) 0%, transparent 60%)',
                        animation: 'pulseGlow 6s ease-in-out infinite alternate',
                    }}
                />

                {/* Dot grid */}
                <div
                    className="absolute inset-0 pointer-events-none opacity-40"
                    style={{
                        backgroundImage: 'radial-gradient(circle, #1e2f50 1px, transparent 1px)',
                        backgroundSize: '28px 28px',
                    }}
                />

                {/* Navbar */}
                <nav className="relative z-10 flex items-center justify-between px-8 py-5 border-b border-sidebar-border">
                    <div className="flex items-center gap-2.5">
                        <div className="w-8 h-8 bg-brand rounded-lg flex items-center justify-center flex-shrink-0">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                            </svg>
                        </div>
                        <span className="font-bold text-[17px] tracking-tight">ASAMBLI</span>
                    </div>

                    {auth?.user ? (
                        <Link
                            href={
                                auth.user.rol === 'super_admin'   ? route('super-admin.tenants.index') :
                                auth.user.rol === 'administrador' ? route('admin.dashboard') :
                                                                    route('sala.index')
                            }
                            className="px-4 py-2 bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold rounded transition-colors"
                        >
                            Ir al Dashboard →
                        </Link>
                    ) : (
                        <Link
                            href={route('login')}
                            className="px-4 py-2 bg-brand hover:bg-brand-dark text-white text-[13px] font-semibold rounded transition-colors"
                        >
                            Iniciar sesión →
                        </Link>
                    )}
                </nav>

                {/* Hero */}
                <div className="relative z-10 max-w-4xl mx-auto px-8 pt-24 pb-20 text-center">

                    {/* Badge */}
                    <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border mb-8 text-[12px] font-medium tracking-wide"
                        style={{ borderColor: 'rgba(37,99,235,0.3)', background: 'rgba(37,99,235,0.08)', color: '#93c5fd' }}
                    >
                        <span
                            className="w-1.5 h-1.5 rounded-full bg-green-400"
                            style={{ animation: 'pulseDot 2s ease-in-out infinite' }}
                        />
                        Votaciones en tiempo real con WebSockets
                    </div>

                    {/* Headline */}
                    <h1
                        className="text-[clamp(36px,5vw,62px)] font-extrabold tracking-[-2px] leading-[1.08] mb-6"
                        style={{
                            background: 'linear-gradient(135deg, #e8edf5 0%, #93c5fd 100%)',
                            WebkitBackgroundClip: 'text',
                            WebkitTextFillColor: 'transparent',
                            backgroundClip: 'text',
                        }}
                    >
                        Gestión de Asambleas<br />de Propiedad Horizontal
                    </h1>

                    <p className="text-lg text-sidebar-text leading-relaxed max-w-xl mx-auto mb-10">
                        Conduce asambleas con quórum dinámico, votaciones ponderadas
                        y reportes auditables con firma SHA-256.
                    </p>

                    <Link
                        href={route('login')}
                        className="inline-flex items-center gap-2 px-8 py-3.5 bg-brand hover:bg-brand-dark text-white text-base font-semibold rounded-[10px] transition-all duration-200 hover:-translate-y-0.5"
                        style={{ boxShadow: '0 4px 20px rgba(37,99,235,0.35)' }}
                    >
                        Comenzar ahora
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </Link>
                </div>

                {/* Feature cards */}
                <div className="relative z-10 max-w-5xl mx-auto px-8 pb-20 grid grid-cols-1 md:grid-cols-3 gap-5">
                    {FEATURES.map((f, i) => (
                        <div
                            key={i}
                            className="p-7 border border-sidebar-border rounded-[14px] transition-all duration-200 hover:border-brand/40 hover:-translate-y-1 cursor-default"
                            style={{ background: 'rgba(22, 30, 48, 0.7)', backdropFilter: 'blur(8px)' }}
                        >
                            <div
                                className="w-11 h-11 rounded-[10px] flex items-center justify-center mb-4"
                                style={{ background: 'rgba(37,99,235,0.12)', border: '1px solid rgba(37,99,235,0.2)', color: '#93c5fd' }}
                            >
                                {f.icon}
                            </div>
                            <h3 className="text-base font-bold text-sidebar-text-active mb-2">{f.title}</h3>
                            <p className="text-sm text-sidebar-text leading-relaxed">{f.desc}</p>
                        </div>
                    ))}
                </div>
            </div>

            <style>{`
                @keyframes pulseGlow {
                    from { opacity: 0.8; transform: scale(1); }
                    to   { opacity: 1; transform: scale(1.05); }
                }
                @keyframes pulseDot {
                    0%, 100% { opacity: 1; transform: scale(1); }
                    50%       { opacity: 0.4; transform: scale(0.8); }
                }
            `}</style>
        </>
    )
}
