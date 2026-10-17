<?php

namespace App\Modules\Compensaciones\Domain\Repositories;

use App\Modules\Compensaciones\Domain\Entities\Categoria;
use App\Modules\Compensaciones\Domain\Exceptions\CodigoDeCategoriaEnUso;
use App\Modules\Compensaciones\Domain\Exceptions\NombreDeCategoriaRepetido;
use App\Modules\Compensaciones\Domain\ValueObjects\EstadoCategoria;
use App\Modules\Compensaciones\Domain\ValueObjects\FiltroCategorias;
use App\Modules\Compensaciones\Domain\ValueObjects\PaginaCategorias;

interface CategoriaRepository
{
    /**
     * Ejecuta la acción en una transacción: el cambio, sus indicadores y su auditoría se confirman juntos.
     *
     * @template T
     *
     * @param  callable(): T  $accion
     * @return T
     */
    public function enTransaccion(callable $accion): mixed;

    /** Listado paginado de 15; una página fuera de rango devuelve la última. Sin indicadores. */
    public function buscar(FiltroCategorias $filtro): PaginaCategorias;

    /** Categoría en cualquier estado, con sus indicadores en orden. */
    public function porId(int $id): ?Categoria;

    /**
     * Categorías existentes entre los ids dados, en cualquier estado y con indicadores.
     *
     * @param  array<int, int>  $ids
     * @return array<int, Categoria>
     */
    public function porIds(array $ids): array;

    /**
     * Categorías en estado Activo con sus indicadores, por código.
     *
     * @return array<int, Categoria>
     */
    public function activas(): array;

    /** Indica si el nombre normalizado ya existe (activas e inactivas), excluyendo opcionalmente una categoría. */
    public function existeNombre(string $nombreNormalizado, ?int $excluirId = null): bool;

    /** Siguiente código CAT-### disponible (mínimo tres dígitos). */
    public function siguienteCodigo(): string;

    /**
     * @throws CodigoDeCategoriaEnUso
     * @throws NombreDeCategoriaRepetido
     */
    public function crear(Categoria $nueva, string $codigo, int $actorId): Categoria;

    /**
     * Aplica los datos propuestos solo si la categoría sigue en la versión leída (UPDATE condicional).
     * Reemplaza la lista de indicadores. Devuelve null si otro proceso la modificó antes.
     */
    public function actualizarSiVigente(Categoria $actual, Categoria $propuesta, int $actorId): ?Categoria;

    /** Cambia el estado solo si la categoría sigue en la versión leída. Devuelve null si cambió antes. */
    public function cambiarEstadoSiVigente(Categoria $actual, EstadoCategoria $estado, int $actorId): ?Categoria;
}
