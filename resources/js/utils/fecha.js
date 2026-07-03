const TZ = 'America/Bogota'
const LOCALE = 'es-CO'

const safe = (iso, formatter) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return isNaN(d) ? '—' : formatter.format(d)
}

const fmtFechaCorta = new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium', timeZone: TZ })
const fmtFechaHora  = new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ })
const fmtHora       = new Intl.DateTimeFormat(LOCALE, { timeStyle: 'short', timeZone: TZ })

export const fechaCorta = (iso) => safe(iso, fmtFechaCorta)   // 2 jul 2026
export const fechaHora  = (iso) => safe(iso, fmtFechaHora)    // 2 jul 2026, 3:45 p. m.
export const hora       = (iso) => safe(iso, fmtHora)         // 3:45 p. m.
