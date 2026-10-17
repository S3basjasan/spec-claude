<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Domain\Entities\ProgresoMicro;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoDisponible;
use App\Modules\Desarrollo\Domain\Exceptions\MicroYaCompletado;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;

/** «Marcar como completado» de un Video, PDF o Artículo. */
final class CompletarMicro
{
    public function __construct(
        private readonly AccesoDeColaborador $acceso,
        private readonly ProgresoMicroRepositoryInterface $progresos,
    ) {}

    public function ejecutar(int $microcontenidoId, int $usuarioId): ProgresoMicro
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

        $progreso = $this->progresos->iniciarOGuardarAvance($microcontenidoId, $cuenta['colaborador_id'], $cuenta['tenant_id'], null);

        return $this->progresos->completar($progreso->id);
    }
}
