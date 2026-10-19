const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic']
const MESES_LARGOS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']

export const SIN_DATO = '—'

function partes(iso: string): [number, number, number] {
  const [anio, mes, dia] = iso.slice(0, 10).split('-').map(Number)
  return [anio, mes, dia]
}

/** «5 mar 2026» a partir de «2026-03-05»; «—» si no hay fecha. */
export function fecha(iso: string | null | undefined): string {
  if (!iso) return SIN_DATO
  const [anio, mes, dia] = partes(iso)
  return `${dia} ${MESES_CORTOS[mes - 1]} ${anio}`
}

/** «mar 2026» a partir de «2026-03». */
export function mes(isoMes: string): string {
  const [anio, m] = isoMes.split('-').map(Number)
  return `${MESES_CORTOS[m - 1]} ${anio}`
}

/** «marzo de 2026» a partir de «2026-03». */
export function mesLargo(isoMes: string): string {
  const [anio, m] = isoMes.split('-').map(Number)
  return `${MESES_LARGOS[m - 1]} de ${anio}`
}

/** Número con coma decimal y punto de miles, con los decimales indicados. */
export function numero(valor: number | null | undefined, decimales = 0): string {
  if (valor === null || valor === undefined) return SIN_DATO
  const [entera, decimal] = valor.toFixed(decimales).split('.')
  const miles = entera.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  return decimal === undefined ? miles : `${miles},${decimal}`
}

export function dolares(valor: number | null | undefined, decimales = 0): string {
  return valor === null || valor === undefined ? SIN_DATO : `$${numero(valor, decimales)}`
}

export function horas(valor: number | null | undefined): string {
  return valor === null || valor === undefined ? SIN_DATO : `${numero(valor, Number.isInteger(valor) ? 0 : 1)} h`
}

export function porcentaje(valor: number | null | undefined, decimales = 1): string {
  return valor === null || valor === undefined ? SIN_DATO : `${numero(valor, decimales)} %`
}

/** Promedio de 1 a 5 con un decimal. */
export function promedio(valor: number | null | undefined): string {
  return valor === null || valor === undefined ? SIN_DATO : numero(valor, 1)
}

/** Diferencia con signo explícito («+2,5» / «−1,0»). */
export function conSigno(valor: number | null | undefined, decimales = 1): string {
  if (valor === null || valor === undefined) return SIN_DATO
  if (valor === 0) return numero(0, decimales)
  return `${valor > 0 ? '+' : '−'}${numero(Math.abs(valor), decimales)}`
}

/** Rango del año actual, valor por defecto de los reportes. */
export function rangoDelAnio(hoy: Date = new Date()): { desde: string; hasta: string } {
  const anio = hoy.getFullYear()
  return { desde: `${anio}-01-01`, hasta: `${anio}-12-31` }
}
