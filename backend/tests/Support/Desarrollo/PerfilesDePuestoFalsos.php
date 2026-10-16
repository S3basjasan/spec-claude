<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\PerfilesDePuestoInterface;

/** Puerto falso de perfiles de puesto; cuenta cuántas veces se consultó cada puesto. */
final class PerfilesDePuestoFalsos implements PerfilesDePuestoInterface
{
    /** @var array<int, int> */
    public array $consultas = [];

    /**
     * @param  array<int, array<string, mixed>>  $perfiles  puesto_id => perfil
     */
    public function __construct(public array $perfiles = []) {}

    public function perfilDe(int $puestoId): ?array
    {
        $this->consultas[$puestoId] = ($this->consultas[$puestoId] ?? 0) + 1;

        return $this->perfiles[$puestoId] ?? null;
    }
}
