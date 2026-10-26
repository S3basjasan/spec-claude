<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Domain\Entities\Actuacion;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Repositories\ActuacionRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Reconocimiento de reintentos por `Idempotency-Key`: una repetición con el mismo contenido y el mismo actor devuelve
 * el resultado anterior; reutilizar la clave para otra actuación u otro contenido es un conflicto.
 */
final class ControlDeOperaciones
{
    public function __construct(private readonly ActuacionRepositoryInterface $actuaciones) {}

    /** @throws ClaveDeOperacionRequerida */
    public function normalizar(?string $clave): string
    {
        if ($clave === null || ! Str::isUuid($clave)) {
            throw new ClaveDeOperacionRequerida;
        }

        return strtolower($clave);
    }

    /** Huella del contenido de una operación. */
    public function huella(mixed ...$partes): string
    {
        return hash('sha256', (string) json_encode($partes, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  int|null  $actuacionEsperada  Para resoluciones: la actuación sobre la que se opera.
     * @return Actuacion|null La actuación resultante de una repetición idéntica; null si la clave es nueva.
     *
     * @throws ClaveDeOperacionReutilizada
     */
    public function repeticion(int $tenantId, string $clave, string $huella, int $actorId, ?int $actuacionEsperada = null): ?Actuacion
    {
        $presentacion = $this->actuaciones->porOperacion($tenantId, $clave);
        if ($presentacion !== null) {
            if ($actuacionEsperada !== null || $presentacion['hash'] !== $huella || $presentacion['actor'] !== $actorId) {
                throw new ClaveDeOperacionReutilizada;
            }

            return $presentacion['actuacion'];
        }

        $transicion = $this->actuaciones->transicionPorOperacion($tenantId, $clave);
        if ($transicion !== null) {
            if ($transicion['hash'] !== $huella || $transicion['actor_id'] !== $actorId
                || ($actuacionEsperada !== null && $transicion['actuacion_id'] !== $actuacionEsperada)) {
                throw new ClaveDeOperacionReutilizada;
            }

            return $this->actuaciones->porId($tenantId, $transicion['actuacion_id']);
        }

        return null;
    }
}
