<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\EvaluacionDelDestinoPort;
use DateTimeImmutable;

/**
 * Mientras no exista el contrato con el módulo evaluador no hay evidencia que consultar: la aprobación de ascensos y
 * traslados queda impedida, sin suplirla con una afirmación manual.
 */
final class EvaluacionDelDestinoNoInstalada implements EvaluacionDelDestinoPort
{
    public function consultar(int $tenantId, int $colaboradorId, int $posicionDestinoId, DateTimeImmutable $instante): array
    {
        return ['estado' => 'NoDisponible', 'contexto' => null];
    }
}
