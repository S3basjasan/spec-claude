<?php

namespace App\Modules\Desarrollo\Domain\Services;

use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use DateTimeImmutable;

/** Cuenta los hitos Pendientes con fecha anterior a hoy (FR-025). */
final class HitosVencidos
{
    /** @param  list<HitoDePdi>  $hitos */
    public function contar(array $hitos, DateTimeImmutable $hoy): int
    {
        return count(array_filter($hitos, fn (HitoDePdi $h) => $h->estaVencido($hoy)));
    }
}
