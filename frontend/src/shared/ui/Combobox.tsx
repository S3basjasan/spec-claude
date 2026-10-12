import { useId, useMemo, useState, type KeyboardEvent } from 'react'

export interface ComboboxOption {
  value: string
  label: string
  description?: string
}

interface ComboboxProps {
  id: string
  label: string
  options: ComboboxOption[]
  /** Valor elegido, o `null` si no hay selección. */
  value: string | null
  onChange: (value: string | null) => void
  error?: string
  placeholder?: string
  /** Texto de la lista cuando nada coincide con lo escrito. */
  emptyText?: string
  disabled?: boolean
}

/** Compara sin distinguir mayúsculas ni acentos. */
const normalizar = (texto: string) => texto.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()

/**
 * Selector con búsqueda (design/DESIGN.md §4): filtra al escribir; flechas mueven la opción activa,
 * Enter la elige y Escape cierra sin cambiar la selección.
 */
export function Combobox({
  id,
  label,
  options,
  value,
  onChange,
  error,
  placeholder,
  emptyText = 'Sin resultados',
  disabled = false,
}: ComboboxProps) {
  const listId = useId()
  // Lo que se está escribiendo; `null` = mostrar la etiqueta de la opción elegida.
  const [consulta, setConsulta] = useState<string | null>(null)
  const [abierto, setAbierto] = useState(false)
  const [activo, setActivo] = useState(0)

  const elegida = options.find((opcion) => opcion.value === value)
  const texto = consulta ?? elegida?.label ?? ''

  const filtradas = useMemo(() => {
    if (consulta === null || consulta.trim() === '') return options
    const buscado = normalizar(consulta.trim())
    return options.filter((opcion) => normalizar(opcion.label).includes(buscado))
  }, [options, consulta])

  const cerrar = () => {
    setAbierto(false)
    setConsulta(null)
  }

  const elegir = (opcion: ComboboxOption) => {
    onChange(opcion.value)
    cerrar()
  }

  const alTeclear = (evento: KeyboardEvent<HTMLInputElement>) => {
    if (evento.key === 'ArrowDown') {
      evento.preventDefault()
      if (!abierto) setAbierto(true)
      else setActivo((actual) => Math.min(actual + 1, filtradas.length - 1))
    } else if (evento.key === 'ArrowUp') {
      evento.preventDefault()
      setActivo((actual) => Math.max(actual - 1, 0))
    } else if (evento.key === 'Enter') {
      // Con la lista abierta, Enter elige y no envía el formulario.
      if (abierto) {
        evento.preventDefault()
        const opcion = filtradas[activo]
        if (opcion) elegir(opcion)
      }
    } else if (evento.key === 'Escape') {
      if (abierto) {
        evento.preventDefault()
        cerrar()
      }
    }
  }

  const activaId = abierto && filtradas[activo] ? `${id}-opcion-${filtradas[activo].value}` : undefined

  return (
    <div className="relative flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-semibold">
        {label}
      </label>
      <input
        id={id}
        type="text"
        role="combobox"
        autoComplete="off"
        aria-autocomplete="list"
        aria-expanded={abierto}
        aria-controls={listId}
        aria-activedescendant={activaId}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? `${id}-error` : undefined}
        disabled={disabled}
        placeholder={placeholder}
        value={texto}
        onChange={(evento) => {
          setConsulta(evento.target.value)
          setAbierto(true)
          setActivo(0)
          if (evento.target.value === '' && value !== null) onChange(null)
        }}
        onFocus={() => setAbierto(true)}
        onClick={() => setAbierto(true)}
        onBlur={cerrar}
        onKeyDown={alTeclear}
        className={`h-11 rounded-lg border bg-surface px-3 text-[15px] text-text placeholder:text-muted ${
          error ? 'border-danger' : 'border-input-border'
        }`}
      />
      {abierto && (
        <div className="absolute inset-x-0 top-full z-10 mt-1 max-h-60 overflow-y-auto rounded-xl border border-border bg-surface py-1 shadow-lg">
          <ul id={listId} role="listbox" aria-label={label}>
            {filtradas.map((opcion, indice) => (
              <li
                key={opcion.value}
                id={`${id}-opcion-${opcion.value}`}
                role="option"
                aria-selected={opcion.value === value}
                // `mousedown` evita que el campo pierda el foco antes de elegir.
                onMouseDown={(evento) => {
                  evento.preventDefault()
                  elegir(opcion)
                }}
                onMouseEnter={() => setActivo(indice)}
                className={`cursor-pointer px-3 py-2 ${indice === activo ? 'bg-soft' : ''}`}
              >
                <span className="block text-[15px]">{opcion.label}</span>
                {opcion.description && <span className="block text-[13px] text-muted">{opcion.description}</span>}
              </li>
            ))}
          </ul>
          {filtradas.length === 0 && (
            <p role="status" className="px-3 py-2 text-[13px] text-muted">
              {emptyText}
            </p>
          )}
        </div>
      )}
      {error && (
        <p id={`${id}-error`} className="text-[13px] text-danger">
          {error}
        </p>
      )}
    </div>
  )
}
