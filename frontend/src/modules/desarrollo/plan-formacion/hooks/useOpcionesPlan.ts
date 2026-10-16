import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { buscarColaboradores, listarCursos, obtenerOpciones } from '../api'
import { planesKey } from './claves'

/** Aprobadores, tipos y competencias activas del asistente. */
export function useOpcionesPlan() {
  return useQuery({
    queryKey: [...planesKey, 'opciones'],
    queryFn: obtenerOpciones,
  })
}

/** Búsqueda de colaboradores con vínculo vigente (solo se consulta con el buscador abierto). */
export function useBuscarColaboradores(texto: string, habilitado: boolean) {
  return useQuery({
    queryKey: [...planesKey, 'colaboradores', texto],
    queryFn: () => buscarColaboradores(texto, 1, 20),
    enabled: habilitado,
    placeholderData: keepPreviousData,
  })
}

/** Cursos del catálogo como punto de partida de una acción. */
export function useCursosParaPlan(habilitado: boolean) {
  return useQuery({
    queryKey: [...planesKey, 'cursos'],
    queryFn: () => listarCursos(),
    enabled: habilitado,
  })
}
