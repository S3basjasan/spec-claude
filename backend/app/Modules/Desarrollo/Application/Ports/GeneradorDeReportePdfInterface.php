<?php

namespace App\Modules\Desarrollo\Application\Ports;

use App\Modules\Desarrollo\Domain\Exceptions\ExportacionNoDisponible;

/** Genera el PDF del reporte: indicadores y tablas, sin gráficos. */
interface GeneradorDeReportePdfInterface
{
    /**
     * @param  array{titulo: string, subtitulo: string, tablas: list<array{titulo: string, columnas: list<string>, filas: list<list<string|int|float|bool|null>>}>}  $datos
     * @return string contenido binario del PDF
     *
     * @throws ExportacionNoDisponible
     */
    public function generar(array $datos): string;
}
