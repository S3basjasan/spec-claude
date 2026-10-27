import { useEffect, useMemo, useState } from 'react'
import { Combobox } from '../../../../shared/ui/Combobox'
import { SearchField } from '../../../../shared/ui/SearchField'
import { useBuscarColaboradores } from '../hooks/useOffboarding'

const RETARDO_BUSQUEDA_MS = 300

interface SelectorDeColaboradorProps {
  id: string
  label: string
  placeholder: string
  valor: number | null
  onChange: (id: number | null) => void
  error?: string
  hint?: string
}

/** Búsqueda remota de personas de la organización más selector con búsqueda. */
export function SelectorDeColaborador({ id, label, placeholder, valor, onChange, error, hint }: SelectorDeColaboradorProps) {
  const [texto, setTexto] = useState('')
  const [consulta, setConsulta] = useState('')

  useEffect(() => {
    const espera = setTimeout(() => setConsulta(texto.trim()), RETARDO_BUSQUEDA_MS)
    return () => clearTimeout(espera)
  }, [texto])

  const personas = useBuscarColaboradores(consulta)
  const opciones = useMemo(() => (personas.data ?? []).map((p) => ({ value: String(p.id), label: `${p.nombre} (${p.codigo})` })), [personas.data])

  return (
    <div className="flex flex-col gap-2">
      <SearchField id={`${id}-busqueda`} label={`Buscar ${label.toLowerCase()}`} placeholder={placeholder} value={texto} onChange={(e) => setTexto(e.target.value)} maxLength={50} />
      <Combobox id={id} label={label} options={opciones} value={valor === null ? null : String(valor)} onChange={(nuevo) => onChange(nuevo === null ? null : Number(nuevo))} placeholder={placeholder} error={error} />
      {hint && <p className="text-[13px] text-muted">{hint}</p>}
    </div>
  )
}
