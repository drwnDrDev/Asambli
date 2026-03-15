export default function ProyeccionLayout({ children, onTickerToggle, tickerVisible }) {
    return (
        <div className="min-h-screen bg-gray-900 text-white p-8 relative">
            {/* Ticker toggle — discrete button in top-right corner */}
            {onTickerToggle !== undefined && (
                <button
                    onClick={onTickerToggle}
                    className="absolute top-4 right-4 text-xs text-gray-500 hover:text-gray-300 transition px-2 py-1 rounded border border-gray-700 hover:border-gray-500"
                    title={tickerVisible ? 'Ocultar ticker' : 'Mostrar ticker'}
                >
                    {tickerVisible ? '👁 Ticker ON' : '👁 Ticker OFF'}
                </button>
            )}
            {children}
        </div>
    )
}
