import { useState } from 'react'
import { Alert } from '../../../../shared/ui/Alert'
import { ErrorState } from '../../../../shared/ui/ErrorState'
import { Skeleton } from '../../../../shared/ui/Skeleton'
import { useDesarrollo } from '../hooks/useDesarrollo'
import { desarrolloText } from '../text'
import { BloqueMovilidad } from './BloqueMovilidad'
import { FormularioExperiencia } from './FormularioExperiencia'
import { FormularioFormacion } from './FormularioFormacion'
import { FormularioMovilidad } from './FormularioMovilidad'
import { ListaExperiencia } from './ListaExperiencia'
import { ListaFormacion } from './ListaFormacion'

type Dialogo =
  | { tipo: 'formacion'; id: number | null }
  | { tipo: 'experiencia'; id: number | null }
  | { tipo: 'movilidad' }
  | null

interface SeccionDesarrolloProps {
  /** Ficha a mostrar; `null` = la propia (Mi ficha), siempre de solo lectura. */
  colaboradorId: number | null
}

/**
 * Pestaña «Desarrollo»: formación, experiencia y movilidad. Consulta su propio endpoint, así que un fallo muestra
 * error con Reintentar sin ocultar el resto de la ficha.
 */
export function SeccionDesarrollo({ colaboradorId }: SeccionDesarrolloProps) {
  const consulta = useDesarrollo(colaboradorId)
  const [dialogo, setDialogo] = useState<Dialogo>(null)
  const [aviso, setAviso] = useState<string | null>(null)

  if (consulta.isPending) {
    return (
      <div role="status" aria-label={desarrolloText.loading} className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-5">
        {[0, 1, 2].map((fila) => (
          <Skeleton key={fila} className="h-10" />
        ))}
      </div>
    )
  }

  if (consulta.isError) {
    return (
      <div className="rounded-xl border border-border bg-surface">
        <ErrorState
          title={desarrolloText.errorTitle}
          description={desarrolloText.errorDescription}
          retryLabel={desarrolloText.retry}
          onRetry={() => void consulta.refetch()}
        />
      </div>
    )
  }

  const desarrollo = consulta.data
  const editable = desarrollo.acciones.editar && colaboradorId !== null
  const cerrar = () => setDialogo(null)
  const guardado = () => {
    setDialogo(null)
    setAviso(desarrolloText.saved)
  }
  const actualizar = () => void consulta.refetch()
  const abrir = (siguiente: Dialogo) => {
    setAviso(null)
    setDialogo(siguiente)
  }

  // La fila se busca en los datos vigentes: tras «Actualizar datos» el formulario toma la versión nueva sin perder lo escrito.
  const formacion = dialogo?.tipo === 'formacion' && dialogo.id !== null ? desarrollo.formaciones.find((f) => f.id === dialogo.id) : undefined
  const experiencia = dialogo?.tipo === 'experiencia' && dialogo.id !== null ? desarrollo.experiencias.find((e) => e.id === dialogo.id) : undefined

  return (
    <div className="flex flex-col gap-5">
      {aviso && (
        <div role="status">
          <Alert tone="neutral">{aviso}</Alert>
        </div>
      )}

      <ListaFormacion
        formaciones={desarrollo.formaciones}
        editable={editable}
        onAgregar={() => abrir({ tipo: 'formacion', id: null })}
        onEditar={(fila) => abrir({ tipo: 'formacion', id: fila.id })}
      />
      <ListaExperiencia
        experiencias={desarrollo.experiencias}
        editable={editable}
        onAgregar={() => abrir({ tipo: 'experiencia', id: null })}
        onEditar={(fila) => abrir({ tipo: 'experiencia', id: fila.id })}
      />
      <BloqueMovilidad movilidad={desarrollo.movilidad} editable={editable} onActualizar={() => abrir({ tipo: 'movilidad' })} />

      {editable && colaboradorId !== null && dialogo?.tipo === 'formacion' && (dialogo.id === null || formacion) && (
        <FormularioFormacion
          colaboradorId={colaboradorId}
          hoy={desarrollo.hoy}
          fila={formacion}
          onCerrar={cerrar}
          onGuardado={guardado}
          onActualizar={actualizar}
        />
      )}
      {editable && colaboradorId !== null && dialogo?.tipo === 'experiencia' && (dialogo.id === null || experiencia) && (
        <FormularioExperiencia
          colaboradorId={colaboradorId}
          hoy={desarrollo.hoy}
          fila={experiencia}
          onCerrar={cerrar}
          onGuardado={guardado}
          onActualizar={actualizar}
        />
      )}
      {editable && colaboradorId !== null && dialogo?.tipo === 'movilidad' && (
        <FormularioMovilidad
          colaboradorId={colaboradorId}
          movilidad={desarrollo.movilidad}
          onCerrar={cerrar}
          onGuardado={guardado}
          onActualizar={actualizar}
        />
      )}
    </div>
  )
}
