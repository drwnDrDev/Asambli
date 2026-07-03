const etiquetas = {
    simple: 'Mayoría simple (>50% de votos emitidos)',
    calificada_70: 'Mayoría calificada (70% del coeficiente del edificio)',
    unanimidad: 'Unanimidad (100% de votos emitidos)',
}

export default function ResultadosMayoria({ mayoriaData }) {
    if (!mayoriaData) return null

    const { tipo_mayoria, resultado_tentativo } = mayoriaData
    const esAprobada = resultado_tentativo === 'aprobada'

    return (
        <div className={`mt-3 p-3 rounded-lg border ${esAprobada ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'}`}>
            <div className="flex items-center justify-between mb-2">
                <span className="text-xs text-gray-500 font-medium">
                    {etiquetas[tipo_mayoria] ?? tipo_mayoria}
                </span>
                <span className={`text-sm font-bold ${esAprobada ? 'text-green-700' : 'text-red-700'}`}>
                    {esAprobada ? '✓ Aprobada (tentativo)' : '✗ Rechazada (tentativo)'}
                </span>
            </div>

            {tipo_mayoria === 'simple' && (
                <div className="text-xs text-gray-600">
                    {mayoriaData.porcentaje_favor?.toFixed(1)}% a favor
                    {' '}(umbral: {mayoriaData.umbral}%)
                </div>
            )}

            {tipo_mayoria === 'calificada_70' && (
                <div className="text-xs text-gray-600">
                    {mayoriaData.porcentaje_sobre_edificio?.toFixed(1)}% del edificio a favor
                    {' '}(umbral: {mayoriaData.umbral}%)
                </div>
            )}

            {tipo_mayoria === 'unanimidad' && (
                <div className="text-xs text-gray-600">
                    {mayoriaData.votos_en_contra > 0
                        ? `${mayoriaData.votos_en_contra} en contra — no unánime`
                        : 'Todos a favor'}
                </div>
            )}
        </div>
    )
}
