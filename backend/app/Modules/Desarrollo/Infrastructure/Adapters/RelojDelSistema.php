<?php

namespace App\Modules\Desarrollo\Infrastructure\Adapters;

use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Shared\Presentation\Facades\SharedFacade;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Hoy en la zona horaria del tenant del usuario (UTC si no se puede resolver). */
final class RelojDelSistema implements RelojInterface
{
    public function hoy(int $usuarioId): DateTimeImmutable
    {
        $zona = SharedFacade::getContextoDeTenant($usuarioId)['zona_horaria'] ?? 'UTC';
        try {
            $tz = new DateTimeZone($zona);
        } catch (Throwable) {
            $tz = new DateTimeZone('UTC');
        }

        return new DateTimeImmutable('today', $tz);
    }
}
