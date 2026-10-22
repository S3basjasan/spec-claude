import { useState, type FormEvent } from 'react'
import { Button } from '../../../../shared/ui/Button'
import { TextField } from '../../../../shared/ui/TextField'
import { MAX_NOMBRE_NIVEL, type Nivel } from '../api'
import { rutasText } from '../text'
import { aNumero, tieneErrores, validarNivel, type CampoNivel, type ErroresDe } from '../validation'

const t = rutasText.detalle.propios

interface DatosDelNivelProps {
  nivel: Nivel
  /** Guarda el nombre y la experiencia mínima; devuelve un error general si la operación falla. */
  onGuardar: (nombre: string, experiencia: number | null) => void
  pendiente: boolean
  /** Error por campo o general devuelto por el servidor al guardar. */
  errores?: ErroresDe<CampoNivel>
}

/**
 * Nombre y experiencia mínima del nivel: lo único propio de la ruta. El puesto, las competencias y los requisitos se
 * gestionan en Estructura Empresarial. Se monta con `key` por nivel para partir de cero al elegir otro.
 */
export function DatosDelNivel({ nivel, onGuardar, pendiente, errores: delServidor }: DatosDelNivelProps) {
  const [nombre, setNombre] = useState(nivel.nombre)
  const [experiencia, setExperiencia] = useState(nivel.experiencia_minima_anios === null ? '' : String(nivel.experiencia_minima_anios).replace('.', ','))
  const [locales, setLocales] = useState<ErroresDe<CampoNivel>>({})
  const errores = { ...delServidor, ...locales }

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (pendiente) return
    const encontrados = validarNivel({ nombre, experiencia })
    setLocales(encontrados)
    if (tieneErrores(encontrados)) return
    onGuardar(nombre.trim(), aNumero(experiencia))
  }

  return (
    <form onSubmit={enviar} noValidate className="flex flex-col gap-3 rounded-xl border border-border bg-surface p-3.5">
      <h3 className="m-0 text-sm text-accent">{t.titulo}</h3>
      <div className="grid gap-3 sm:grid-cols-2 [&>*]:min-w-0">
        <TextField id="nivel-nombre" label={t.nombre} value={nombre} onChange={(e) => setNombre(e.target.value)} error={errores.nombre} maxLength={MAX_NOMBRE_NIVEL + 20} />
        <TextField
          id="nivel-experiencia"
          label={t.experiencia}
          value={experiencia}
          onChange={(e) => setExperiencia(e.target.value)}
          error={errores.experiencia}
          hint={t.experienciaAyuda}
          inputMode="decimal"
        />
      </div>
      <p className="m-0 text-xs text-muted">{t.soloLectura}</p>
      <div>
        <Button type="submit" variant="secondary" loading={pendiente}>
          {t.guardar}
        </Button>
      </div>
    </form>
  )
}
