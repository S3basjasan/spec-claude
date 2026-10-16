<?php

namespace App\Modules\GestionPersonal\Presentation\Facades;

use App\Modules\GestionPersonal\Application\Services\PersonalService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static array|null getColaborador(int $tenantId, int $id) Ficha {id, codigo, identificación, nombres, apellidos, version} del tenant, o null.
 * @method static array getColaboradoresPorIds(int $tenantId, array $ids) Fichas existentes del tenant entre los ids dados.
 * @method static array|null buscarPorIdentificacion(int $tenantId, string $tipo, string $numero) Ficha con esa identificación normalizada, o null.
 * @method static array|null getCuentaDeUsuario(int $usuarioId) {tenant_id, colaborador_id} de la ficha asociada a la cuenta del usuario, o null si no tiene.
 * @method static array posicionesConAsignacionVigenteOFutura(array $posicionIds, string $fecha) Posiciones con asignación vigente o futura en la fecha.
 * @method static array registrarFicha(int $tenantId, int $actorId, string $claveDeOperacion, array $datos) Alta idempotente (GP-07) con las mismas reglas del alta manual.
 * @method static array getDesarrollo(int $tenantId, array $colaboradorIds) Formación, experiencia y movilidad por colaborador (GP-06); lanza InformacionNoDisponible si la lectura falla.
 * @method static array getCategoriasActivas(int $tenantId) Categorías documentales Activas {id, nombre} para cargas nuevas (GP-05); lanza InformacionNoDisponible si la lectura falla.
 * @method static array getCategoriasPorIds(int $tenantId, array $ids) Nombre vigente {id, nombre, estado} de categorías de cualquier estado.
 * @method static void confirmarCategoriaActiva(int $tenantId, int $categoriaId) Bloquea la categoría activa dentro de la transacción de publicación; lanza CategoriaInactiva.
 *
 * @see PersonalService
 */
class PersonalFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'personal-facade';
    }
}
