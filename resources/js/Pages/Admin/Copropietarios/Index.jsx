import AdminLayout from '@/Layouts/AdminLayout'
import { Link, usePage, router } from '@inertiajs/react'
import { useState, useEffect, useRef } from 'react'

export default function Index({ copropietarios, filters }) {
    const { flash } = usePage().props
    const [search, setSearch] = useState(filters.search ?? '')
    const debounceRef = useRef(null)

    const activeTab = filters.tab ?? 'copropietarios'

    const goTo = (params) => {
        router.get('/admin/copropietarios', { ...filters, ...params }, {
            preserveState: true, replace: true,
        })
    }

    useEffect(() => {
        clearTimeout(debounceRef.current)
        debounceRef.current = setTimeout(() => {
            if (search !== filters.search) goTo({ search, page: 1 })
        }, 350)
        return () => clearTimeout(debounceRef.current)
    }, [search])

    const destroy = (c) => {
        const esExterno = c.es_externo
        let msg = `¿Eliminar ${esExterno ? 'este delegado externo' : 'este copropietario'}?`
        if (esExterno) {
            msg += '\n\nNota: si este externo tiene un poder activo en una reunión vigente, la eliminación será bloqueada.'
        }
        if (confirm(msg)) {
            router.delete(`/admin/copropietarios/${c.id}`, { preserveScroll: true })
        }
    }

    const tabs = [
        { key: 'copropietarios', label: 'Copropietarios' },
        { key: 'externos', label: 'Delegados Externos' },
    ]

    return (
        <AdminLayout title="Copropietarios">
            {flash?.success && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-success-bg border border-success text-success text-sm">
                    {flash.success}
                </div>
            )}
            {flash?.error && (
                <div className="mb-4 px-4 py-3 rounded-lg bg-danger-bg border border-danger text-danger text-sm">
                    {flash.error}
                </div>
            )}

            <div className="flex flex-wrap justify-between items-center gap-4 mb-5">
                {/* Tabs */}
                <div className="flex border border-surface-border rounded-lg overflow-hidden">
                    {tabs.map(t => (
                        <button
                            key={t.key}
                            onClick={() => goTo({ tab: t.key, search: '', page: 1 })}
                            className={`px-4 py-2 text-sm font-medium transition-colors ${
                                activeTab === t.key
                                    ? 'bg-brand text-white'
                                    : 'bg-surface text-app-text-secondary hover:bg-surface-hover'
                            }`}
                        >
                            {t.label}
                        </button>
                    ))}
                </div>

                <div className="flex gap-3 items-center">
                    {/* Búsqueda */}
                    <div className="relative">
                        <svg className="absolute left-3 top-1/2 -translate-y-1/2 text-app-text-muted w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input
                            type="text"
                            placeholder="Buscar por nombre, email o documento..."
                            value={search}
                            onChange={e => setSearch(e.target.value)}
                            className="pl-9 pr-4 py-2 text-sm border border-surface-border rounded-lg bg-surface text-app-text-primary focus:outline-none focus:ring-2 focus:ring-brand/30 w-72"
                        />
                    </div>

                    {activeTab === 'copropietarios' && (
                        <Link
                            href="/admin/copropietarios/create"
                            className="inline-flex items-center gap-2 px-4 py-2 bg-brand hover:bg-brand-dark text-white text-sm font-semibold rounded-lg transition-colors"
                        >
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Nuevo
                        </Link>
                    )}
                </div>
            </div>

            <div className="bg-surface rounded-xl border border-surface-border overflow-hidden">
                {copropietarios.data.length === 0 ? (
                    <div className="text-center py-16 text-app-text-muted">
                        <p className="text-sm">
                            {search ? `Sin resultados para "${search}".` : `No hay ${activeTab === 'externos' ? 'delegados externos' : 'copropietarios'} registrados.`}
                        </p>
                    </div>
                ) : activeTab === 'copropietarios' ? (
                    <CopropietariosTable data={copropietarios.data} onDestroy={destroy} />
                ) : (
                    <ExternosTable data={copropietarios.data} onDestroy={destroy} />
                )}

                <Pagination meta={copropietarios} />
            </div>
        </AdminLayout>
    )
}

function CopropietariosTable({ data, onDestroy }) {
    return (
        <table className="w-full text-sm">
            <thead className="bg-content-bg border-b border-surface-border">
                <tr>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Nombre</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Documento</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Unidades</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Coef. total</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Estado</th>
                    <th className="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody className="divide-y divide-surface-border">
                {data.map(c => {
                    const coefTotal = (c.unidades ?? []).reduce((s, u) => s + parseFloat(u.coeficiente ?? 0), 0)
                    return (
                        <tr key={c.id} className="hover:bg-surface-hover transition-colors">
                            <td className="px-5 py-3.5">
                                <div className="font-medium text-app-text-primary">{c.nombre}</div>
                                <div className="text-xs text-app-text-muted">{c.email}</div>
                            </td>
                            <td className="px-5 py-3.5 text-app-text-secondary">
                                {c.tipo_documento && c.numero_documento
                                    ? `${c.tipo_documento} ${c.numero_documento}`
                                    : '—'}
                            </td>
                            <td className="px-5 py-3.5 text-app-text-secondary">
                                {(c.unidades ?? []).length > 0
                                    ? c.unidades.map(u => u.numero).join(', ')
                                    : <span className="text-app-text-muted">Sin asignar</span>}
                            </td>
                            <td className="px-5 py-3.5 font-mono text-app-text-secondary text-xs">
                                {coefTotal > 0 ? `${coefTotal.toFixed(5)}%` : '—'}
                            </td>
                            <td className="px-5 py-3.5">
                                <div className="flex flex-col gap-1">
                                    <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.activo ? 'bg-success-bg text-success' : 'bg-danger-bg text-danger'}`}>
                                        {c.activo ? 'Activo' : 'Inactivo'}
                                    </span>
                                    {c.poderes_activos_count > 0 && (
                                        <span className="text-[11px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">Con poder</span>
                                    )}
                                </div>
                            </td>
                            <td className="px-5 py-3.5">
                                <div className="flex items-center justify-end gap-3">
                                    <Link href={`/admin/copropietarios/${c.id}`} className="text-xs text-brand hover:underline">Ver</Link>
                                    <Link href={`/admin/copropietarios/${c.id}/edit`} className="text-xs text-app-text-secondary hover:text-brand">Editar</Link>
                                    <button onClick={() => onDestroy(c)} className="text-xs text-app-text-muted hover:text-danger transition-colors">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    )
                })}
            </tbody>
        </table>
    )
}

function ExternosTable({ data, onDestroy }) {
    const poderEstadoStyles = {
        aprobado:  'bg-success-bg text-success',
        revocado:  'bg-danger-bg text-danger',
        rechazado: 'bg-danger-bg text-danger',
        expirado:  'bg-surface-border text-app-text-muted',
        pendiente: 'bg-warning/20 text-warning',
    }

    return (
        <table className="w-full text-sm">
            <thead className="bg-content-bg border-b border-surface-border">
                <tr>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Nombre</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Documento</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Empresa / Teléfono</th>
                    <th className="text-left px-5 py-3 font-medium text-app-text-muted">Último poder</th>
                    <th className="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody className="divide-y divide-surface-border">
                {data.map(c => {
                    const ultimoPoder = c.ultimo_poder_como_apoderado ?? null
                    const estadoPoder = ultimoPoder?.estado
                    return (
                        <tr key={c.id} className="hover:bg-surface-hover transition-colors">
                            <td className="px-5 py-3.5">
                                <div className="flex items-center gap-2">
                                    <span className="font-medium text-app-text-primary">{c.nombre}</span>
                                    <span className="text-[11px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded font-medium">Externo</span>
                                </div>
                                <div className="text-xs text-app-text-muted">{c.email}</div>
                            </td>
                            <td className="px-5 py-3.5 text-app-text-secondary">
                                {c.tipo_documento && c.numero_documento ? `${c.tipo_documento} ${c.numero_documento}` : '—'}
                            </td>
                            <td className="px-5 py-3.5 text-app-text-secondary text-xs">
                                {c.empresa && <div>{c.empresa}</div>}
                                {c.telefono && <div className="text-app-text-muted">{c.telefono}</div>}
                                {!c.empresa && !c.telefono && '—'}
                            </td>
                            <td className="px-5 py-3.5">
                                {estadoPoder ? (
                                    <div>
                                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${poderEstadoStyles[estadoPoder] ?? ''}`}>
                                            {estadoPoder.charAt(0).toUpperCase() + estadoPoder.slice(1)}
                                        </span>
                                        {ultimoPoder?.reunion && (
                                            <div className="text-[11px] text-app-text-muted mt-0.5">{ultimoPoder.reunion.titulo}</div>
                                        )}
                                    </div>
                                ) : (
                                    <span className="text-app-text-muted text-xs">Sin poder</span>
                                )}
                            </td>
                            <td className="px-5 py-3.5">
                                <div className="flex items-center justify-end gap-3">
                                    <Link href={`/admin/copropietarios/${c.id}`} className="text-xs text-brand hover:underline">Ver</Link>
                                    <button onClick={() => onDestroy(c)} className="text-xs text-app-text-muted hover:text-danger transition-colors">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                    )
                })}
            </tbody>
        </table>
    )
}

function Pagination({ meta }) {
    if (meta.last_page <= 1) return null
    return (
        <div className="px-5 py-4 border-t border-surface-border flex justify-between items-center text-sm">
            <span className="text-app-text-muted text-xs">
                Mostrando {meta.from}–{meta.to} de {meta.total}
            </span>
            <div className="flex gap-1.5">
                {meta.links.map((link, i) => (
                    <button
                        key={i}
                        disabled={!link.url}
                        onClick={() => link.url && router.get(link.url, {}, { preserveState: true })}
                        className={`px-3 py-1 text-xs rounded border transition-colors ${
                            link.active
                                ? 'bg-brand text-white border-brand'
                                : 'border-surface-border text-app-text-secondary hover:bg-surface-hover disabled:opacity-40'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    )
}
