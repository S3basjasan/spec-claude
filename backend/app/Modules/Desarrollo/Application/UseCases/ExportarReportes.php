<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\GeneradorDeExcelInterface;
use App\Modules\Desarrollo\Application\Ports\GeneradorDeReportePdfInterface;
use App\Modules\Desarrollo\Domain\Exceptions\FormatoDeExportacionInvalido;
use App\Modules\Desarrollo\Domain\ValueObjects\FiltroReportes;

/**
 * Exporta con los filtros aplicados los mismos datos que muestra la pantalla: indicadores, detalle, impacto 30-60-90 y
 * evaluaciones de impacto. El Excel lleva las cuatro hojas; el PDF, los indicadores y las tablas, sin gráficos.
 * Las exportaciones no se registran.
 */
final class ExportarReportes
{
    private const HITOS = [30, 60, 90];

    public function __construct(
        private readonly ObtenerDashboard $dashboard,
        private readonly ListarDetalle $detalle,
        private readonly ListarSeguimiento $seguimiento,
        private readonly ListarImpactos $impactos,
        private readonly GeneradorDeExcelInterface $excel,
        private readonly GeneradorDeReportePdfInterface $pdf,
    ) {}

    /**
     * @return array{contenido: string, tipo: string, nombre: string}
     */
    public function ejecutar(int $tenantId, int $usuarioId, FiltroReportes $filtro, string $formato): array
    {
        if (! in_array($formato, ['xlsx', 'pdf'], true)) {
            throw new FormatoDeExportacionInvalido;
        }

        $tablas = $this->tablas($tenantId, $usuarioId, $filtro);
        $periodo = $filtro->periodo->aArreglo();
        $nombre = 'reportes-evaluacion-'.$periodo['desde'].'_'.$periodo['hasta'];

        if ($formato === 'xlsx') {
            $hojas = array_map(fn (array $t) => ['nombre' => $t['hoja'], 'filas' => [$t['columnas'], ...$t['filas']]], $tablas);

            return ['contenido' => $this->excel->generar($hojas), 'tipo' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'nombre' => $nombre.'.xlsx'];
        }

        return [
            'contenido' => $this->pdf->generar([
                'titulo' => 'Reportes de evaluación de resultados',
                'subtitulo' => 'Período del '.$periodo['desde'].' al '.$periodo['hasta'],
                'tablas' => array_map(fn (array $t) => ['titulo' => $t['titulo'], 'columnas' => $t['columnas'], 'filas' => $t['filas']], $tablas),
            ]),
            'tipo' => 'application/pdf',
            'nombre' => $nombre.'.pdf',
        ];
    }

    /**
     * @return list<array{hoja: string, titulo: string, columnas: list<string>, filas: list<list<string|int|float|bool|null>>}>
     */
    private function tablas(int $tenantId, int $usuarioId, FiltroReportes $filtro): array
    {
        return [
            $this->indicadores($tenantId, $usuarioId, $filtro),
            [
                'hoja' => 'Detalle', 'titulo' => 'Detalle por actividad',
                'columnas' => ['Código', 'Actividad', 'Tipo', 'Inicio', 'Fin', 'Departamento destinatario', 'Asistentes', 'Inscritos', 'Horas-persona', 'Costo (USD)', 'Calificación promedio', 'Satisfacción promedio', 'Respuestas de satisfacción'],
                'filas' => array_map(fn (array $f) => [
                    $f['codigo'], $f['nombre'], $f['tipo'], $f['fecha_inicio'], $f['fecha_fin'], $f['departamento'], $f['asistentes'], $f['inscritos'],
                    $f['horas_persona'], $f['costo'], $f['calificacion_promedio'], $f['satisfaccion_promedio'], $f['respuestas_satisfaccion'],
                ], $this->detalle->todasLasFilas($tenantId, $usuarioId, $filtro)),
            ],
            [
                'hoja' => 'Impacto 30-60-90', 'titulo' => 'Impacto a 30, 60 y 90 días',
                'columnas' => $this->columnasDeSeguimiento(),
                'filas' => array_map(fn (array $f) => $this->filaDeSeguimiento($f), $this->seguimiento->todasLasFilas($tenantId, $usuarioId, $filtro)),
            ],
            [
                'hoja' => 'Evaluación de impacto', 'titulo' => 'Evaluación de impacto por acción',
                'columnas' => ['Plan', 'Acción', 'Tipo', 'Fin', 'Costo (USD)', 'Evaluada', 'Indicador de negocio', 'Unidad', 'Valor antes', 'Valor después', 'Beneficio estimado (USD)', 'ROI (%)', 'Justificación', 'Creada por', 'Modificada por'],
                'filas' => array_map(fn (array $f) => [
                    $f['plan_codigo'], $f['accion_nombre'], $f['tipo'], $f['fecha_fin'], $f['costo'], $f['evaluada'],
                    $f['impacto']['indicador'] ?? null, $f['impacto']['unidad'] ?? null, $f['impacto']['valor_antes'] ?? null, $f['impacto']['valor_despues'] ?? null,
                    $f['impacto']['beneficio_estimado'] ?? null, $f['roi'], $f['impacto']['justificacion'] ?? null, $f['impacto']['creado_por'] ?? null, $f['impacto']['modificado_por'] ?? null,
                ], $this->impactos->todas($tenantId, $usuarioId, $filtro->planId)),
            ],
        ];
    }

    /** @return array{hoja: string, titulo: string, columnas: list<string>, filas: list<list<string|int|float|bool|null>>} */
    private function indicadores(int $tenantId, int $usuarioId, FiltroReportes $filtro): array
    {
        $d = $this->dashboard->ejecutar($tenantId, $usuarioId, $filtro);
        $nombres = [
            'plan_cumplido' => '% Plan cumplido', 'total_horas' => 'Total horas', 'satisfaccion' => 'Satisfacción promedio (1-5)',
            'brechas_cerradas' => 'Brechas cerradas', 'roi_estimado' => 'ROI estimado (%)',
            'inversion_por_colaborador' => 'Inversión por colaborador formado (USD)', 'horas_por_colaborador' => 'Horas por colaborador formado', 'costo_por_hora' => 'Costo por hora (USD)',
        ];

        $filas = [];
        foreach ([...array_keys($d['indicadores']), ...array_keys($d['gestion'])] as $clave) {
            $i = $d['indicadores'][$clave] ?? $d['gestion'][$clave];
            $filas[] = [
                $nombres[$clave], $i['disponible'] ? $i['valor'] : 'No disponible', $i['anterior'], $i['diferencia'], $i['variacion_pct'],
            ];
        }
        $filas[] = ['Brechas detectadas en el período', $d['indicadores']['brechas_cerradas']['detectadas'], null, null, null];
        $filas[] = ['Período', $d['periodo']['desde'].' a '.$d['periodo']['hasta'], $d['periodo_anterior']['desde'].' a '.$d['periodo_anterior']['hasta'], null, null];
        $filas[] = ['Filtros', $this->filtros($filtro), null, null, null];

        return [
            'hoja' => 'Indicadores', 'titulo' => 'Indicadores',
            'columnas' => ['Indicador', 'Valor', 'Período anterior', 'Diferencia', 'Variación (%)'],
            'filas' => $filas,
        ];
    }

    private function filtros(FiltroReportes $f): string
    {
        $partes = [];
        if ($f->departamentoId !== null) {
            $partes[] = 'departamento '.$f->departamentoId;
        }
        if ($f->tipo !== null) {
            $partes[] = 'tipo '.$f->tipo;
        }
        if ($f->planId !== null) {
            $partes[] = 'plan '.$f->planId;
        }

        return $partes === [] ? 'Sin filtros adicionales' : implode(', ', $partes);
    }

    /** @return list<string> */
    private function columnasDeSeguimiento(): array
    {
        $columnas = ['Código', 'Actividad', 'Fin', 'Colaboradores elegibles', 'Alerta de reingreso'];
        foreach (self::HITOS as $h) {
            array_push($columnas, "$h días: respuestas", "$h días: tasa de respuesta (%)", "$h días: aplicación", "$h días: apoyo");
        }

        return $columnas;
    }

    /**
     * @param  array<string, mixed>  $f
     * @return list<string|int|float|bool|null>
     */
    private function filaDeSeguimiento(array $f): array
    {
        $fila = [$f['codigo'], $f['nombre'], $f['fecha_fin'], $f['elegibles'], $f['alerta_reingreso']];
        foreach ($f['hitos'] as $h) {
            array_push($fila, $h['respuestas'], $h['tasa_respuesta'], $h['promedio_aplicacion'], $h['promedio_apoyo']);
        }

        return $fila;
    }
}
