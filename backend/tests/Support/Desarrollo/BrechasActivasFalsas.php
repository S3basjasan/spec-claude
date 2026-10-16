<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\BrechasActivasInterface;
use RuntimeException;

/** Puerto falso de brechas para pruebas; puede simular un fallo de la DNF. */
final class BrechasActivasFalsas implements BrechasActivasInterface
{
    /** @var array<int, int> */
    public array $consultados = [];

    /**
     * @param  array<int, array{competencia_id: int, nivel_requerido: string}>  $brechas
     */
    public function __construct(private readonly array $brechas, private readonly bool $falla = false) {}

    public function brechasDe(int $usuarioId): array
    {
        $this->consultados[] = $usuarioId;

        if ($this->falla) {
            throw new RuntimeException('DNF no disponible.');
        }

        return $this->brechas;
    }
}
