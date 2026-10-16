import type { ReactNode } from 'react'
import type { ColaboradorDetalle } from '../api'
import { fichasText } from '../text'
import { DatoOpcional } from './DatoOpcional'
import { SeccionLaboral } from './SeccionLaboral'
import { Trayectoria } from './Trayectoria'

const labels = fichasText.detail.labels
const groups = fichasText.detail.groups

function Grupo({ titulo, children }: { titulo: string; children: ReactNode }) {
  return (
    <section aria-labelledby={`grupo-${titulo}`} className="rounded-xl border border-border bg-surface p-5">
      <h2 id={`grupo-${titulo}`} className="mb-4 text-base font-bold">
        {titulo}
      </h2>
      {children}
    </section>
  )
}

function Par({ etiqueta, children }: { etiqueta: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <dt className="text-[13px] font-semibold text-muted">{etiqueta}</dt>
      <dd className="text-[15px]">{children}</dd>
    </div>
  )
}

const lista = 'grid grid-cols-1 gap-4 sm:grid-cols-2'

interface FichaDatosProps {
  ficha: ColaboradorDetalle
  onReintentar: () => void
}

/** Sección «Personal y puesto»: identificación, datos personales, contacto, emergencia, puesto y trayectoria. */
export function FichaDatos({ ficha, onReintentar }: FichaDatosProps) {
  const tipo = fichasText.identificationTypes[ficha.tipo_identificacion] ?? ficha.tipo_identificacion

  return (
    <div className="flex flex-col gap-5">
      <Grupo titulo={groups.identification}>
        <dl className={lista}>
          <Par etiqueta={labels.type}>{tipo}</Par>
          <Par etiqueta={labels.number}>{ficha.numero_identificacion}</Par>
          <Par etiqueta={labels.firstNames}>{ficha.nombres}</Par>
          <Par etiqueta={labels.lastNames}>{ficha.apellidos}</Par>
        </dl>
      </Grupo>

      <Grupo titulo={groups.personal}>
        <dl className={lista}>
          <Par etiqueta={labels.birthDate}>
            <DatoOpcional valor={ficha.fecha_nacimiento} />
          </Par>
          <Par etiqueta={labels.nationality}>
            <DatoOpcional valor={ficha.nacionalidad} />
          </Par>
          <Par etiqueta={labels.gender}>
            <DatoOpcional valor={ficha.genero} />
          </Par>
          <Par etiqueta={labels.maritalStatus}>
            <DatoOpcional valor={ficha.estado_civil} />
          </Par>
          <Par etiqueta={labels.children}>
            <DatoOpcional valor={ficha.numero_hijos} />
          </Par>
        </dl>
      </Grupo>

      <Grupo titulo={groups.contact}>
        <dl className={lista}>
          <Par etiqueta={labels.address}>
            <DatoOpcional valor={ficha.direccion} />
          </Par>
          <Par etiqueta={labels.phone}>
            <DatoOpcional valor={ficha.telefono} />
          </Par>
          <Par etiqueta={labels.contactEmail}>
            <DatoOpcional valor={ficha.correo_contacto} />
          </Par>
        </dl>
      </Grupo>

      <Grupo titulo={groups.emergency}>
        <dl className={lista}>
          <Par etiqueta={labels.emergencyName}>
            <DatoOpcional valor={ficha.emergencia_nombre} />
          </Par>
          <Par etiqueta={labels.emergencyRelationship}>
            <DatoOpcional valor={ficha.emergencia_relacion} />
          </Par>
          <Par etiqueta={labels.emergencyPhone}>
            <DatoOpcional valor={ficha.emergencia_telefono} />
          </Par>
        </dl>
      </Grupo>

      <Grupo titulo={groups.job}>
        <SeccionLaboral laboral={ficha.laboral} onReintentar={onReintentar} />
      </Grupo>

      <Grupo titulo={groups.trajectory}>
        <Trayectoria trayectoria={ficha.trayectoria} onReintentar={onReintentar} />
      </Grupo>
    </div>
  )
}
