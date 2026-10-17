<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\CuentaColaboradorInterface;
use App\Modules\Desarrollo\Domain\Entities\Microcontenido;
use App\Modules\Desarrollo\Domain\Entities\ProgresoMicro;
use App\Modules\Desarrollo\Domain\Exceptions\ColaboradorSinAcceso;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoEncontrado;
use App\Modules\Desarrollo\Domain\Repositories\MicrocontenidoRepositoryInterface;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;

/**
 * Identifica al colaborador detrás de una cuenta y decide qué micro-contenidos puede ver: los Publicados y los
 * Archivados que ya completó. Todo lo demás se responde como inexistente.
 */
final class AccesoDeColaborador
{
    public function __construct(
        private readonly CuentaColaboradorInterface $cuentas,
        private readonly MicrocontenidoRepositoryInterface $contenidos,
        private readonly ProgresoMicroRepositoryInterface $progresos,
    ) {}

    /**
     * @return array{tenant_id: int, colaborador_id: int}
     *
     * @throws ColaboradorSinAcceso
     */
    public function cuenta(int $usuarioId): array
    {
        return $this->cuentas->cuentaDe($usuarioId) ?? throw new ColaboradorSinAcceso;
    }

    /**
     * Micro-contenido visible para el colaborador junto con su progreso (si existe).
     *
     * @return array{micro: Microcontenido, progreso: ProgresoMicro|null, cuenta: array{tenant_id: int, colaborador_id: int}}
     *
     * @throws ColaboradorSinAcceso
     * @throws MicroNoEncontrado
     */
    public function visible(int $microcontenidoId, int $usuarioId): array
    {
        $cuenta = $this->cuenta($usuarioId);
        $micro = $this->contenidos->obtener($microcontenidoId) ?? throw new MicroNoEncontrado;
        $progreso = $this->progresos->progresoDe($microcontenidoId, $cuenta['colaborador_id']);

        $visible = $micro->estado === EstadoMicro::Publicado
            || ($micro->estado === EstadoMicro::Archivado && $progreso?->estaCompletado() === true);
        if (! $visible) {
            throw new MicroNoEncontrado;
        }

        return ['micro' => $micro, 'progreso' => $progreso, 'cuenta' => $cuenta];
    }
}
