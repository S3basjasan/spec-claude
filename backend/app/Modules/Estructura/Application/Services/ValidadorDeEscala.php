<?php

namespace App\Modules\Estructura\Application\Services;

use App\Modules\Estructura\Domain\Exceptions\CategoriasInvalidas;
use App\Modules\Estructura\Domain\Exceptions\EscalaNoEncontrada;
use App\Modules\Estructura\Domain\Repositories\EscalaRepositoryInterface;
use App\Modules\Estructura\Domain\Services\ReglasDeCategorias;

/**
 * Comprueba la escala elegida y sus nombres de categoría: con la escala Categorías los cuatro nombres propios
 * son obligatorios; en las demás son opcionales, pero si se envían deben ser válidos.
 */
final class ValidadorDeEscala
{
    public function __construct(private readonly EscalaRepositoryInterface $escalas) {}

    /**
     * @param  array<int, string>|null  $categorias
     * @return array<int, string>|null Nombres recortados o null si no se enviaron.
     *
     * @throws EscalaNoEncontrada
     * @throws CategoriasInvalidas
     */
    public function categoriasValidas(int $escalaId, ?array $categorias): ?array
    {
        $escala = $this->escalas->obtener($escalaId) ?? throw new EscalaNoEncontrada;

        if ($categorias === null) {
            if ($escala->esCategorias()) {
                throw new CategoriasInvalidas;
            }

            return null;
        }

        $limpias = array_map('trim', array_values($categorias));
        ReglasDeCategorias::validar($limpias);

        return $limpias;
    }
}
