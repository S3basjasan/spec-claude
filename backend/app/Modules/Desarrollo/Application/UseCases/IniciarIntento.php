<?php

namespace App\Modules\Desarrollo\Application\UseCases;

use App\Modules\Desarrollo\Application\Services\AccesoDeColaborador;
use App\Modules\Desarrollo\Domain\Entities\IntentoQuiz;
use App\Modules\Desarrollo\Domain\Entities\Microcontenido;
use App\Modules\Desarrollo\Domain\Exceptions\MicroNoDisponible;
use App\Modules\Desarrollo\Domain\Repositories\ProgresoMicroRepositoryInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoMicro;

/**
 * Inicia (o retoma) el intento En curso de un Quiz y crea el progreso si no existe. Con `$reiniciar` descarta el
 * intento En curso anterior. Un intento solo cuenta cuando se responde la última pregunta.
 */
final class IniciarIntento
{
    public function __construct(
        private readonly AccesoDeColaborador $acceso,
        private readonly ProgresoMicroRepositoryInterface $progresos,
    ) {}

    /**
     * @return array{intento: IntentoQuiz, micro: Microcontenido, nuevo: bool}
     */
    public function ejecutar(int $microcontenidoId, int $usuarioId, bool $reiniciar = false): array
    {
        ['micro' => $micro, 'cuenta' => $cuenta] = $this->acceso->visible($microcontenidoId, $usuarioId);

        if ($micro->estado !== EstadoMicro::Publicado) {
            throw new MicroNoDisponible;
        }
        if (! $micro->tipo->esQuiz() || $micro->preguntas === []) {
            throw new MicroNoDisponible('Este contenido no es un quiz disponible.');
        }

        $progreso = $this->progresos->iniciarOGuardarAvance($microcontenidoId, $cuenta['colaborador_id'], $cuenta['tenant_id'], null);

        if ($reiniciar) {
            $this->progresos->descartarIntentosEnCurso($progreso->id);
        }

        $vigente = $this->progresos->intentoEnCurso($progreso->id);
        if ($vigente !== null) {
            return ['intento' => $vigente, 'micro' => $micro, 'nuevo' => false];
        }

        return [
            'intento' => $this->progresos->crearIntento($progreso->id, count($micro->preguntas)),
            'micro' => $micro,
            'nuevo' => true,
        ];
    }
}
