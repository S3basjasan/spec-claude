<?php

namespace App\Modules\Compensaciones\Presentation\Facades;

use App\Modules\Compensaciones\Application\Services\CategoriasService;
use Illuminate\Support\Facades\Facade;

/**
 * Contrato público del catálogo de categorías de reconocimiento.
 *
 * @method static array listarSeleccionables() Categorías Activas con competencia activa (únicas válidas para nuevos registros).
 * @method static array|null obtener(int $id) Categoría en cualquier estado, o null; resuelve registros históricos.
 * @method static array obtenerPorIds(array $ids) Categorías existentes entre los ids dados, en cualquier estado.
 * @method static bool esSeleccionable(int $id) Indica si existe, está Activa y su competencia está activa.
 *
 * @see CategoriasService
 */
class CategoriasFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'compensaciones-categorias-facade';
    }
}
