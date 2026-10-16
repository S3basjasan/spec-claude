import type { Brecha } from '../api'
import { kpisDe } from '../indicadores'
import { filtrarBrechas, type FiltrosDiagnostico as Filtros } from '../orden'
import { FiltrosDiagnostico } from './FiltrosDiagnostico'
import { GraficoPerfil } from './GraficoPerfil'
import { KpiDiagnostico } from './KpiDiagnostico'
import { dnfText } from '../text'
import { TablaDiagnostico } from './TablaDiagnostico'

interface PanelDiagnosticoProps {
  nombreColaborador: string
  brechas: Brecha[]
  filtros: Filtros
  onFiltros: (filtros: Filtros) => void
}

/** Diagnóstico de un colaborador: indicadores, filtros, gráfico perfil ideal vs actual y tabla. Reutilizable en solo lectura. */
export function PanelDiagnostico({ nombreColaborador, brechas, filtros, onFiltros }: PanelDiagnosticoProps) {
  const filtradas = filtrarBrechas(brechas, filtros)

  return (
    <div className="flex flex-col gap-5">
      <KpiDiagnostico items={kpisDe(brechas)} />
      <GraficoPerfil nombreColaborador={nombreColaborador} brechas={filtradas} />
      <FiltrosDiagnostico filtros={filtros} onChange={onFiltros} />
      <TablaDiagnostico brechas={filtradas} />
      <p className="text-xs leading-relaxed text-muted">{dnfText.admin.tabla.calculo}</p>
    </div>
  )
}
