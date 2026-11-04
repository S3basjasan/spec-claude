<?php

namespace App\Modules\Compensaciones\Application\UseCases;

use App\Modules\Compensaciones\Application\DTOs\CategoriaDTO;
use App\Modules\Compensaciones\Application\Services\AlcanceDeLectura;
use App\Modules\Compensaciones\Application\Services\ArmadorDeCategoriaDTO;
use App\Modules\Compensaciones\Domain\Exceptions\CategoriaNoEncontrada;
use App\Modules\Compensaciones\Domain\Repositories\CategoriaRepository;

final class ObtenerCategoria
{
    public function __construct(
        private readonly CategoriaRepository $categorias,
        private readonly ArmadorDeCategoriaDTO $armador,
    ) {}

    /**
     * El Gestor consulta cualquiera, también inactivas. El líder, las activas. El colaborador, las activas asignadas a
     * su unidad. Fuera de su alcance responde igual que si no existiera. Los registros históricos se resuelven por
     * `CategoriasFacade`, que no filtra por unidad.
     *
     * @throws CategoriaNoEncontrada
     */
    public function ejecutar(int $id, AlcanceDeLectura $alcance): CategoriaDTO
    {
        $categoria = $this->categorias->porId($id) ?? throw new CategoriaNoEncontrada;

        $visible = match (true) {
            $alcance->esGestor() => true,
            $alcance->esLider() => $categoria->estaActiva() && $categoria->unidades !== null,
            $alcance->esNinguno() => false,
            default => $categoria->estaActiva() && $categoria->asignadaA((int) $alcance->unidadId),
        };

        if (! $visible) {
            throw new CategoriaNoEncontrada;
        }

        return $this->armador->uno($categoria, $alcance->veUnidades());
    }
}
