<?php

namespace App\Modules\Desarrollo\Application\Ports;

use App\Modules\Desarrollo\Domain\Exceptions\ExportacionNoDisponible;

/** Genera un libro de Excel (.xlsx) a partir de hojas de celdas. */
interface GeneradorDeExcelInterface
{
    /**
     * La primera fila de cada hoja es el encabezado.
     *
     * @param  list<array{nombre: string, filas: list<list<string|int|float|bool|null>>}>  $hojas
     * @return string contenido binario del .xlsx
     *
     * @throws ExportacionNoDisponible
     */
    public function generar(array $hojas): string;
}
