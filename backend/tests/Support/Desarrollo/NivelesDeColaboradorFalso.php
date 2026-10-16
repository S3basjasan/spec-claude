<?php

namespace Tests\Support\Desarrollo;

use App\Modules\Desarrollo\Application\Ports\NivelesDeColaboradorInterface;
use RuntimeException;

/** Puerto falso de niveles para pruebas; guarda los ids de usuario con los que fue consultado. */
final class NivelesDeColaboradorFalso implements NivelesDeColaboradorInterface
{
    /** @var array<int, int> */
    public array $consultados = [];

    /**
     * @param  array<int, string>  $niveles
     */
    public function __construct(private readonly array $niveles, private readonly bool $falla = false) {}

    public function nivelesDe(int $usuarioId): array
    {
        $this->consultados[] = $usuarioId;

        if ($this->falla) {
            throw new RuntimeException('Gestión de Personal no disponible.');
        }

        return $this->niveles;
    }
}
