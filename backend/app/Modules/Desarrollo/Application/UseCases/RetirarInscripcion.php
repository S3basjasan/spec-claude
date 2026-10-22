<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\ArmadorDeInscripciones;
use App\Modules\Desarrollo\Application\Services\EventosDeInscripcion;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoEncontrada;
use App\Modules\Desarrollo\Domain\Exceptions\InscripcionNoPermitida;
use App\Modules\Desarrollo\Domain\Exceptions\RutaNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use DateTimeImmutable;

/** Retira una inscripción Activa con un motivo obligatorio. Se permite aunque la ruta esté Archivada. */
final class RetirarInscripcion
{
    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly EventosDeInscripcion $eventos,
        private readonly ArmadorDeInscripciones $armador,
    ) {}

    /**
     * @return array<string, mixed>
     *
     * @throws InscripcionNoEncontrada
     * @throws RutaNoEncontrada
     * @throws InscripcionNoPermitida
     */
    public function ejecutar(int $tenantId, int $usuarioId, int $inscripcionId, string $motivo): array
    {
        $antes = $this->rutas->obtenerInscripcion($tenantId, $inscripcionId) ?? throw new InscripcionNoEncontrada;
        $ruta = $this->rutas->obtener($tenantId, $antes->rutaId) ?? throw new RutaNoEncontrada;

        $despues = $antes->retirada($motivo, $usuarioId, new DateTimeImmutable);
        $guardada = $this->rutas->guardarInscripcion($despues, $this->eventos->cambio('retirada', $antes, $despues, $ruta, $usuarioId, $despues->motivo));

        return $this->armador->detalle($tenantId, $usuarioId, $guardada);
    }
}
