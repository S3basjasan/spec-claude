<?php

namespace App\Modules\GestionPersonal\Application\UseCases;

use App\Modules\GestionPersonal\Application\DTO\ContextoDeTenant;
use App\Modules\GestionPersonal\Application\Ports\ContextoDeLecturaPort;
use App\Modules\GestionPersonal\Application\Ports\RenderizadorDePdfPort;
use App\Modules\GestionPersonal\Application\Services\ArmadorDeSnapshotDeFicha;
use App\Modules\GestionPersonal\Domain\Exceptions\ColaboradorNoEncontrado;
use App\Modules\GestionPersonal\Domain\Exceptions\ExportacionNoDisponible;
use Closure;

/**
 * Exporta una ficha: snapshot de lectura → cierre de la transacción → render en memoria → reautorización inmediata
 * antes de entregar. No escribe nada.
 */
final class GenerarFichaPdf
{
    public function __construct(
        private readonly ContextoDeLecturaPort $lectura,
        private readonly ArmadorDeSnapshotDeFicha $armador,
        private readonly RenderizadorDePdfPort $renderizador,
    ) {}

    /**
     * @param  Closure(): void  $reautorizar  Se ejecuta fuera del snapshot, justo antes de entregar; lanza si se revocó.
     * @return array{contenido: string, nombre: string}
     *
     * @throws ColaboradorNoEncontrado
     * @throws ExportacionNoDisponible
     */
    public function ejecutar(ContextoDeTenant $contexto, int $colaboradorId, Closure $reautorizar): array
    {
        $snapshot = $this->lectura->enSnapshot(fn () => $this->armador->armar($contexto, $colaboradorId));

        $contenido = $this->renderizador->renderizar($snapshot);

        $reautorizar();

        return ['contenido' => $contenido, 'nombre' => $snapshot->nombreDeArchivo];
    }
}
