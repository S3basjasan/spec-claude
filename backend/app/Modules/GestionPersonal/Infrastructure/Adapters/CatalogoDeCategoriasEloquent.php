<?php

namespace App\Modules\GestionPersonal\Infrastructure\Adapters;

use App\Modules\GestionPersonal\Application\Ports\CatalogoDeCategoriasPort;
use App\Modules\GestionPersonal\Domain\Repositories\CategoriaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use Throwable;

final class CatalogoDeCategoriasEloquent implements CatalogoDeCategoriasPort
{
    public function __construct(private readonly CategoriaRepositoryInterface $categorias) {}

    public function activas(int $tenantId): Lectura
    {
        try {
            return Lectura::disponible(array_map(
                fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre],
                $this->categorias->activas($tenantId),
            ));
        } catch (Throwable) {
            return Lectura::noDisponible();
        }
    }

    public function porIds(int $tenantId, array $ids): Lectura
    {
        try {
            $datos = [];
            foreach ($this->categorias->porIds($tenantId, $ids) as $id => $c) {
                $datos[$id] = ['id' => $c->id, 'nombre' => $c->nombre, 'estado' => $c->estado];
            }

            return Lectura::disponible($datos);
        } catch (Throwable) {
            return Lectura::noDisponible();
        }
    }

    public function confirmarActiva(int $tenantId, int $categoriaId): void
    {
        $this->categorias->confirmarActiva($tenantId, $categoriaId);
    }
}
