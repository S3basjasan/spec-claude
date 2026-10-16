<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Services\AutorizadorDeExportacion;
use App\Modules\GestionPersonal\Domain\Exceptions\CuentaSinFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use App\Modules\GestionPersonal\Domain\Exceptions\SinPermisoDeExportacion;

/** Exportación de la ficha propia: la ficha sale de la asociación de la cuenta, nunca de la petición. */
final class GenerarMiFichaPdf
{
    public function __construct(
        private readonly AutorizadorDeExportacion $autorizador,
        private readonly GenerarFichaPdf $generar,
    ) {}

    /**
     * @return array{contenido: string, nombre: string}
     *
     * @throws CuentaSinFicha
     * @throws SinPermisoDeExportacion
     * @throws ExportacionNoDisponible
     */
    public function ejecutar(ContextoDeTenant $contexto, int $userId): array
    {
        $colaboradorId = $this->autorizador->fichaPropia($contexto, $userId);

        return $this->generar->ejecutar($contexto, $colaboradorId, function () use ($contexto, $userId, $colaboradorId): void {
            if ($this->autorizador->fichaPropia($contexto, $userId) !== $colaboradorId) {
                throw new SinPermisoDeExportacion;
            }
        });
    }
}
