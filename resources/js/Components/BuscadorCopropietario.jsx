import { useState } from 'react'

export default function BuscadorCopropietario({
    copropietarios = [],
    seleccionado = null,
    onSeleccionar,
    onLimpiar,
    label = 'Buscar copropietario *',
    placeholder = 'Nombre, documento o unidad…',
    children = null, // contenido extra bajo la tarjeta de seleccionado (ej. elegibilidad)
}) {
    const [busqueda, setBusqueda] = useState('')

    const filtrados = copropietarios.filter(c => {
        const q = busqueda.toLowerCase()
        return (
            c.nombre?.toLowerCase().includes(q) ||
            (c.numero_documento ?? '').toLowerCase().includes(q) ||
            (c.unidades ?? []).some(u => u.numero?.toLowerCase().includes(q))
        )
    })

    const seleccionar = (c) => {
        onSeleccionar(c)
        setBusqueda('')
    }

    if (seleccionado) {
        return (
            <div>
                <div className="flex items-center justify-between border border-gray-200 rounded-lg px-3 py-2.5 bg-white">
                    <div>
                        <p className="text-sm font-medium text-gray-800">
                            {seleccionado.nombre}
                            {seleccionado.en_mora && (
                                <span className="ml-2 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600 border border-red-200">
                                    En mora
                                </span>
                            )}
                        </p>
                        <p className="text-xs text-gray-400 mt-0.5">
                            {seleccionado.numero_documento && `Doc: ${seleccionado.numero_documento} · `}
                            Unidades: {seleccionado.unidades?.map(u => u.numero).join(', ') || '—'}
                        </p>
                    </div>
                    <button type="button" onClick={onLimpiar} className="text-xs text-gray-400 hover:text-red-500 ml-3">✕</button>
                </div>
                {children}
            </div>
        )
    }

    return (
        <div>
            <label className="text-xs text-gray-500 block mb-1">{label}</label>
            <input
                type="text"
                value={busqueda}
                onChange={e => setBusqueda(e.target.value)}
                placeholder={placeholder}
                className="w-full border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 mb-1"
            />
            {busqueda.length > 0 && (
                <div className="border border-gray-200 rounded bg-white max-h-40 overflow-y-auto shadow-sm">
                    {filtrados.length === 0 ? (
                        <p className="text-xs text-gray-400 px-3 py-2">Sin resultados</p>
                    ) : filtrados.map(c => (
                        <button
                            key={c.id}
                            type="button"
                            onClick={() => seleccionar(c)}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 transition border-b border-gray-100 last:border-0"
                        >
                            <span className="font-medium">{c.nombre}</span>
                            {c.en_mora && (
                                <span className="ml-1.5 inline-flex px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-50 text-red-600">en mora</span>
                            )}
                            <span className="text-gray-400 text-xs ml-2">
                                {c.numero_documento && `Doc: ${c.numero_documento} · `}
                                {c.unidades?.map(u => u.numero).join(', ')}
                            </span>
                        </button>
                    ))}
                </div>
            )}
        </div>
    )
}
