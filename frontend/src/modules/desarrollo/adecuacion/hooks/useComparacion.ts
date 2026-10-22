import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { useSearchParams } from 'react-router-dom'
import { comparar, MAX_CANDIDATOS, type ConsultaComparacion, type PesosConsulta } from '../api'
import { adecuacionKey } from './claves'

export function useComparacion(consulta: ConsultaComparacion | null) {
  return useQuery({
    queryKey: [...adecuacionKey, 'comparar', consulta],
    queryFn: () => comparar(consulta as ConsultaComparacion),
    enabled: consulta !== null,
    placeholderData: keepPreviousData,
    retry: false,
  })
}

function enteroPositivo(valor: string | null): number | null {
  const n = Math.floor(Number(valor))

  return Number.isFinite(n) && n > 0 ? n : null
}

/**
 * Estado de la comparación en la URL (compartible): puesto a cubrir, candidatos y, si se editaron, los pesos de la consulta
 * (`pe` experiencia, `pk` conocimientos, `pc` competencias).
 */
export function useEstadoDeComparacion() {
  const [params, setParams] = useSearchParams()
  const puesto = enteroPositivo(params.get('puesto'))
  const candidatos = (params.get('candidatos') ?? '')
    .split(',')
    .map((x) => enteroPositivo(x))
    .filter((x): x is number => x !== null)
    .slice(0, MAX_CANDIDATOS)
  const pesos = { experiencia: params.get('pe'), conocimientos: params.get('pk'), competencias: params.get('pc') }

  const poner = (cambios: Record<string, string | null>) => {
    const siguiente = new URLSearchParams(params)
    for (const [clave, valor] of Object.entries(cambios)) {
      if (valor === null || valor === '') siguiente.delete(clave)
      else siguiente.set(clave, valor)
    }
    setParams(siguiente, { replace: true })
  }

  const elegirPuesto = (id: string) => poner({ puesto: id })
  const alternarCandidato = (id: number) => {
    const siguiente = candidatos.includes(id) ? candidatos.filter((c) => c !== id) : [...candidatos, id]
    poner({ candidatos: siguiente.join(',') })
  }
  // Un peso vacío se conserva en la URL (sigue "editado") para que se pueda borrar y volver a escribir sin que reaparezca el guardado.
  const editarPeso = (dimension: 'experiencia' | 'conocimientos' | 'competencias', texto: string) => {
    const siguiente = new URLSearchParams(params)
    siguiente.set({ experiencia: 'pe', conocimientos: 'pk', competencias: 'pc' }[dimension], texto)
    setParams(siguiente, { replace: true })
  }
  const restablecerPesos = () => poner({ pe: null, pk: null, pc: null })

  return { puesto, candidatos, pesos, elegirPuesto, alternarCandidato, editarPeso, restablecerPesos, hayPesosEditados: pesos.experiencia !== null || pesos.conocimientos !== null || pesos.competencias !== null }
}

export type EstadoDeComparacion = ReturnType<typeof useEstadoDeComparacion>

/** Pesos a enviar: solo si los tres son enteros válidos que suman 100; `undefined` = usar los guardados. */
export function pesosParaEnviar(pesos: EstadoDeComparacion['pesos'], respaldo: PesosConsulta | undefined): { pesos?: PesosConsulta; error?: string; suma: number | null } {
  const editados = pesos.experiencia !== null || pesos.conocimientos !== null || pesos.competencias !== null
  if (!editados) return { suma: respaldo === undefined ? null : respaldo.competencias + respaldo.conocimientos + respaldo.experiencia }

  const valor = (texto: string | null, base: number | undefined): number => (texto === null ? (base ?? 0) : Number(texto))
  const candidato: PesosConsulta = {
    competencias: valor(pesos.competencias, respaldo?.competencias),
    conocimientos: valor(pesos.conocimientos, respaldo?.conocimientos),
    experiencia: valor(pesos.experiencia, respaldo?.experiencia),
  }
  const valores = Object.values(candidato)
  const suma = valores.reduce((a, b) => a + b, 0)
  if (valores.some((v) => !Number.isInteger(v) || v < 0 || v > 100)) return { suma, error: 'entero' }
  if (suma !== 100) return { suma, error: 'suma' }

  return { pesos: candidato, suma }
}
