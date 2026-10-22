<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Domain\Entities\ProgresoMicro;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoDisponible;
use App\Modules\Desarrollo\Domain\Exceptions\MicroYaCompletado;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;

/** Crea o actualiza el progreso En progreso de un Video, PDF o Artículo (y la posición de reproducción del Video). */
final class GuardarAvanceMicro
{
    public function __construct(
        private readonly AccesoDeColaborador $acceso,
        private readonly ProgresoMicroRepositoryInterface $progresos,
    ) {}

    public function ejecutar(int $microcontenidoId, int $usuarioId, ?int $posicionSegundos): ProgresoMicro
    {
        ['micro' => $micro, 'progreso' => $actual, 'cuenta' => $cuenta] = $this->acceso->visible($microcontenidoId, $usuarioId);

        if ($micro->estado !== EstadoMicro::Publicado) {
            throw new MicroNoDisponible;
        }
        if ($micro->tipo->esQuiz()) {
            throw new MicroNoDisponible('Un quiz se completa respondiendo sus preguntas.');
        }
        if ($actual?->estaCompletado() === true) {
            throw new MicroYaCompletado;
        }

        return $this->progresos->iniciarOGuardarAvance(
            $microcontenidoId,
            $cuenta['colaborador_id'],
            $cuenta['tenant_id'],
            $micro->tipo->value === 'Video' ? $posicionSegundos : null,
        );
    }
}
