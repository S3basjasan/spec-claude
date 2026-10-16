import { useQuery } from '@tanstack/react-query'
import { obtenerOpciones } from '../api'
import { dnfKey } from './claves'

/** Colaboradores y departamentos evaluables del paso 1. */
export function useOpcionesDnf() {
  return useQuery({
    queryKey: [...dnfKey, 'opciones'],
    queryFn: obtenerOpciones,
  })
}
