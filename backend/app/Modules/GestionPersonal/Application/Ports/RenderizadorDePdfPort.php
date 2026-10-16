<?php

namespace App\Modules\GestionPersonal\Application\Ports;

use App\Modules\GestionPersonal\Application\DTO\SnapshotDeFichaDTO;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;

interface RenderizadorDePdfPort
{
    /**
     * Devuelve el PDF completo en memoria, nunca un archivo parcial.
     *
     * @throws ExportacionNoDisponible Si falla, o si el resultado está vacío, no empieza por `%PDF-` o no termina en `%%EOF`.
     */
    public function renderizar(SnapshotDeFichaDTO $snapshot): string;
}
