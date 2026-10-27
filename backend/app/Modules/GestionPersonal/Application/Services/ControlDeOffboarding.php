<?php

namespace App\Modules\GestionPersonal\Application\Services;

use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionRequerida;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Reconocimiento de reintentos por `Idempotency-Key`: una repetición con el mismo contenido y actor devuelve el
 * resultado anterior; reutilizar la clave con otro contenido, actor o expediente es un conflicto.
 */
final class ControlDeOffboarding
{
    public function __construct(private readonly ExpedienteRepositoryInterface $expedientes) {}

    /** @throws ClaveDeOperacionRequerida */
    public function normalizar(?string $clave): string
    {
        if ($clave === null || ! Str::isUuid($clave)) {
            throw new ClaveDeOperacionRequerida;
        }

        return strtolower($clave);
    }

    public function huella(mixed ...$partes): string
    {
        return hash('sha256', (string) json_encode($partes, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @throws ClaveDeOperacionReutilizada
     */
    public function repeticion(int $tenantId, string $clave, string $huella, int $actorId, ?int $expedienteEsperado = null): ?Expediente
    {
        $formalizacion = $this->expedientes->porOperacion($tenantId, $clave);
        if ($formalizacion !== null) {
            if ($expedienteEsperado !== null || $formalizacion['hash'] !== $huella || $formalizacion['actor'] !== $actorId) {
                throw new ClaveDeOperacionReutilizada;
            }

            return $formalizacion['expediente'];
        }

        $evento = $this->expedientes->eventoPorOperacion($tenantId, $clave);
        if ($evento !== null) {
            if ($evento['hash'] !== $huella || $evento['actor_id'] !== $actorId || ($expedienteEsperado !== null && $evento['expediente_id'] !== $expedienteEsperado)) {
                throw new ClaveDeOperacionReutilizada;
            }

            return $this->expedientes->porId($tenantId, $evento['expediente_id']);
        }

        return null;
    }
}
