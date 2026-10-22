interface BarrasTriplesProps {
  /** Fracciones de 0 a 1 (o `null` si no aplica) del requisito actual, la evaluación de la persona y el requisito futuro. Sin `actual` se dibujan solo dos barras. */
  actual?: number | null
  persona: number | null
  futuro: number | null
  /** Texto alternativo con los tres valores (el color nunca es la única señal). */
  etiqueta: string
}

function Barra({ valor, color }: { valor: number | null; color: string }) {
  const ancho = valor === null ? 0 : Math.max(0, Math.min(1, valor)) * 100

  return (
    <div className="h-[7px] rounded bg-bg">
      <div className={`h-full rounded ${color}`} style={{ width: `${ancho}%` }} />
    </div>
  )
}

/** Tres barras de 7 px: requisito del puesto actual (`--muted`), evaluación de la persona (`--info`) y requisito futuro (`--warning`). */
export function BarrasTriples({ actual, persona, futuro, etiqueta }: BarrasTriplesProps) {
  return (
    <div role="img" aria-label={etiqueta} className="flex flex-col gap-[3px]">
      {actual !== undefined && <Barra valor={actual} color="bg-muted opacity-60" />}
      <Barra valor={persona} color="bg-info" />
      <Barra valor={futuro} color="bg-warning" />
    </div>
  )
}
