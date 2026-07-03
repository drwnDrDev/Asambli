export default function TipoDecisionSelector({ tiposDecision = [], value, onChange, error }) {
    const etiquetaMayoria = {
        simple: 'Mayoría simple (>50%)',
        calificada_70: 'Mayoría calificada (70% edificio)',
        unanimidad: 'Unanimidad',
    };

    const seleccionado = tiposDecision.find(t => t.id === value);

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">
                Tipo de decisión
            </label>
            <select
                value={value ?? ''}
                onChange={e => onChange(e.target.value ? Number(e.target.value) : null)}
                className="w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
            >
                <option value="">Sin tipo específico (mayoría simple)</option>
                {tiposDecision.map(tipo => (
                    <option key={tipo.id} value={tipo.id}>
                        {tipo.nombre}
                    </option>
                ))}
            </select>
            {seleccionado && (
                <p className="mt-1 text-xs text-indigo-600">
                    Mayoría requerida: {etiquetaMayoria[seleccionado.tipo_mayoria] ?? seleccionado.tipo_mayoria}
                </p>
            )}
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
}
