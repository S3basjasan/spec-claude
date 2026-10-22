<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Services\ArmadorDeAdecuaciones;
use App\Modules\Desarrollo\Domain\Exceptions\AdecuacionNoEncontrada;
use App\Modules\Desarrollo\Domain\Repositories\AdecuacionesRepositoryInterface;
use Throwable;

/** Detalle de solo lectura de una adecuación, con quién la generó y si es la vigente de su colaborador y puesto objetivo. */
final class ObtenerAdecuacion
{
    public function __construct(
        private readonly AdecuacionesRepositoryInterface $adecuaciones,
        private readonly ArmadorDeAdecuaciones $armador,
        private readonly NombresDeUsuarioInterface $usuarios,
    ) {}

    /** @return array<string, mixed> */
    public function ejecutar(int $tenantId, int $id): array
    {
        $a = $this->adecuaciones->obtener($tenantId, $id) ?? throw new AdecuacionNoEncontrada;

        $generadaPor = null;
        try {
            $generadaPor = $this->usuarios->nombresDe([$a->creadoPor])[$a->creadoPor] ?? null;
        } catch (Throwable) {
            // El nombre de quien la generó es informativo: si no se puede leer, se omite.
        }

        $ruta = $a->inscripcionId === null ? null : ($this->adecuaciones->rutasDeInscripciones([$a->inscripcionId])[$a->inscripcionId] ?? null);

        return $this->armador->adecuacion($a, $this->adecuaciones->esVigente($tenantId, $a), $generadaPor, $ruta, $tenantId);
    }
}
