import { fichasText } from '../text'

/** Muestra un dato opcional: «No registrado» si falta; el número 0 se muestra como 0. */
export function DatoOpcional({ valor }: { valor: string | number | null | undefined }) {
  if (valor === null || valor === undefined || valor === '') {
    return <span className="text-muted">{fichasText.notRegistered}</span>
  }
  return <>{valor}</>
}
