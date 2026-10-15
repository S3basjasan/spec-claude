import type { ReactNode } from 'react'
import type { UnidadDetalle, UsuarioDeAuditoria } from '../api'
import { unidadesText } from '../text'

const text = unidadesText.info

/** `2026-10-14` → `14/10/2026`, sin pasar por `Date` para que la zona horaria no mueva el día. */
function formatearFecha(fecha: string): string {
  const [anio, mes, dia] = fecha.slice(0, 10).split('-')
  return `${dia}/${mes}/${anio}`
}

function formatearInstante(instante: string): string {
  return new Intl.DateTimeFormat('es', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false }).format(
    new Date(instante),
  )
}

function quienYCuando(usuario: UsuarioDeAuditoria | null, instante: string | null): string {
  if (usuario === null || instante === null) return text.notModified
  return text.byOn(usuario.nombre ?? text.unknownUser, formatearInstante(instante))
}

function Dato({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <dt className="text-[13px] text-muted">{titulo}</dt>
      <dd className="m-0 text-[15px] font-semibold">{children}</dd>
    </div>
  )
}

interface UnidadInfoProps {
  unidad: UnidadDetalle
}

/** Datos de solo lectura de una unidad al editarla; la auditoría solo llega (y se muestra) al Administrador. */
export function UnidadInfo({ unidad }: UnidadInfoProps) {
  const { auditoria } = unidad

  return (
    <div role="group" aria-label={text.legend} className="rounded-lg border border-border bg-bg p-4">
      <dl className="m-0 grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
        <Dato titulo={text.code}>{unidad.codigo}</Dato>
        <Dato titulo={text.type}>{unidad.tipo.nombre}</Dato>
        <Dato titulo={text.createdOn}>{formatearFecha(unidad.fecha_creacion)}</Dato>
        <Dato titulo={text.suppressedOn}>{unidad.fecha_supresion === null ? text.none : formatearFecha(unidad.fecha_supresion)}</Dato>
        <Dato titulo={text.activeDependents}>{unidad.dependientes_activas}</Dato>
        <Dato titulo={text.positions}>{unidad.posiciones_vigentes}</Dato>
        {auditoria && (
          <>
            <Dato titulo={text.createdBy}>{quienYCuando(auditoria.created_by, auditoria.created_at)}</Dato>
            <Dato titulo={text.modifiedBy}>{quienYCuando(auditoria.updated_by, auditoria.updated_at)}</Dato>
          </>
        )}
      </dl>
    </div>
  )
}
