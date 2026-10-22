import { useState, type FormEvent } from 'react'
import { toApiError } from '../../../../shared/api/httpClient'
import { Alert } from '../../../../shared/ui/Alert'
import { Button } from '../../../../shared/ui/Button'
import { SearchField } from '../../../../shared/ui/SearchField'
import { SelectField } from '../../../../shared/ui/SelectField'
import type { RutaDetalle } from '../api'
import { useInscribir } from '../hooks/useMutacionesRutas'
import { useColaboradoresInscribibles } from '../hooks/useRutas'
import { rutasText } from '../text'

const t = rutasText.inscribir

interface FormularioInscripcionProps {
  ruta: RutaDetalle
  onInscrito: (mensaje: string) => void
}

type Errores = Partial<Record<'colaborador' | 'actual' | 'objetivo', string>>

/**
 * Tarjeta «Inscribir colaborador» de la pestaña Participantes (RutasAdmin.dc.html). Solo se ofrecen colaboradores con
 * vínculo laboral vigente; el nivel actual es opcional y el objetivo debe ser posterior al actual.
 */
export function FormularioInscripcion({ ruta, onInscrito }: FormularioInscripcionProps) {
  const inscribir = useInscribir(ruta.id)
  const [busqueda, setBusqueda] = useState('')
  const [colaborador, setColaborador] = useState('')
  const [actual, setActual] = useState('')
  const [objetivo, setObjetivo] = useState('')
  const [errores, setErrores] = useState<Errores>({})
  const [errorGeneral, setErrorGeneral] = useState<string | undefined>()
  const candidatos = useColaboradoresInscribibles(busqueda.trim())

  const vigente = ruta.estado === 'Vigente'
  const sinNiveles = ruta.niveles.length === 0
  const bloqueada = !vigente || sinNiveles
  const opcionesNivel = [{ value: '', label: t.sinNivel }, ...ruta.niveles.map((n) => ({ value: String(n.id), label: `${rutasText.escalera.nivel(n.orden)} · ${n.nombre}` }))]

  const enviar = (evento: FormEvent) => {
    evento.preventDefault()
    if (inscribir.isPending || bloqueada) return
    setErrorGeneral(undefined)
    if (colaborador === '') {
      setErrores({ colaborador: t.colaboradorRequerido })
      return
    }
    const ordenDe = (id: string) => ruta.niveles.find((n) => String(n.id) === id)?.orden
    const ordenActual = ordenDe(actual)
    const ordenObjetivo = ordenDe(objetivo)
    if (ordenActual !== undefined && ordenObjetivo !== undefined && ordenObjetivo <= ordenActual) {
      setErrores({ objetivo: rutasText.niveles.objetivoPosterior })
      return
    }
    setErrores({})

    const nombre = candidatos.data?.find((c) => String(c.id) === colaborador)?.nombre ?? ''
    inscribir.mutate(
      { ruta_id: ruta.id, colaborador_id: Number(colaborador), nivel_actual_id: actual === '' ? null : Number(actual), nivel_objetivo_id: objetivo === '' ? null : Number(objetivo) },
      {
        onSuccess: () => {
          setColaborador('')
          setActual('')
          setObjetivo('')
          onInscrito(t.exito(nombre))
        },
        onError: (error) => {
          const e = toApiError(error)
          const campo = (clave: string) => e.fieldErrors[clave]?.[0]
          const delServidor: Errores = { colaborador: campo('colaborador_id'), actual: campo('nivel_actual_id'), objetivo: campo('nivel_objetivo_id') }
          setErrores(delServidor)
          setErrorGeneral(Object.values(delServidor).some(Boolean) ? undefined : (e.message ?? t.errorGuardar))
        },
      },
    )
  }

  return (
    <section aria-labelledby="inscribir-titulo" className="flex flex-col gap-3 rounded-xl border border-border bg-surface px-5 py-[18px]">
      <h2 id="inscribir-titulo" className="m-0 text-lg">
        {t.title}
      </h2>
      {!vigente && <Alert tone="neutral">{t.noVigente}</Alert>}
      {vigente && sinNiveles && <Alert tone="neutral">{t.sinNiveles}</Alert>}
      {errorGeneral && <Alert>{errorGeneral}</Alert>}

      <form onSubmit={enviar} noValidate className="flex flex-col gap-3">
        <SearchField id="inscribir-buscar" label={t.buscar} placeholder={t.buscar} value={busqueda} onChange={(e) => setBusqueda(e.target.value)} disabled={bloqueada} />
        <SelectField
          id="inscribir-colaborador"
          label={t.colaborador}
          value={colaborador}
          onChange={(e) => setColaborador(e.target.value)}
          error={errores.colaborador}
          disabled={bloqueada}
          options={[{ value: '', label: candidatos.isPending ? rutasText.loading : t.seleccionar }, ...(candidatos.data ?? []).map((c) => ({ value: String(c.id), label: c.nombre }))]}
        />
        {candidatos.data?.length === 0 && <p className="m-0 text-[13px] text-muted">{t.sinResultados}</p>}
        <div className="grid gap-3 sm:grid-cols-2 [&>*]:min-w-0">
          <SelectField id="inscribir-actual" label={t.nivelActual} value={actual} onChange={(e) => setActual(e.target.value)} error={errores.actual} disabled={bloqueada} options={opcionesNivel} />
          <SelectField id="inscribir-objetivo" label={t.nivelObjetivo} value={objetivo} onChange={(e) => setObjetivo(e.target.value)} error={errores.objetivo} disabled={bloqueada} options={opcionesNivel} />
        </div>
        <p className="m-0 text-[13px] text-muted">{t.ayuda}</p>
        <div>
          <Button type="submit" loading={inscribir.isPending} disabled={bloqueada}>
            {t.boton}
          </Button>
        </div>
      </form>
    </section>
  )
}
