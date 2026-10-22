<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\RelojPort;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Carbon;

/** Reloj sobre `Carbon::now()`: respeta `Carbon::setTestNow` en las pruebas. Siempre en UTC y con precisión de segundos. */
final class RelojDelSistema implements RelojPort
{
    public function ahora(): DateTimeImmutable
    {
        return (new DateTimeImmutable('@'.Carbon::now()->getTimestamp()))->setTimezone(new DateTimeZone('UTC'));
    }
}
