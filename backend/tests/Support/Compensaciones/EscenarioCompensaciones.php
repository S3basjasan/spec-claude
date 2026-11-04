<?php

namespace Tests\Support\Compensaciones;

use App\Modules\Compensaciones\Application\Ports\ColaboradoresPort;
use App\Modules\Compensaciones\Application\Ports\EmpresaActualPort;
use App\Modules\Compensaciones\Application\Ports\EquipoPort;
use App\Modules\Compensaciones\Application\Ports\UnidadesPort;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;

/**
 * Reemplaza los cuatro puertos del módulo por falsos. Unidades por defecto: 1 Operaciones, 2 Ventas, 3 Finanzas
 * (activas) y 4 Tecnología (inactiva).
 */
final class EscenarioCompensaciones
{
    public static ?self $actual = null;

    public function __construct(
        public readonly UnidadesFalso $unidades,
        public readonly ColaboradoresFalso $colaboradores,
        public readonly EquipoFalso $equipo,
    ) {}

    public static function iniciar(): self
    {
        $unidades = new UnidadesFalso;
        $unidades->agregar(1, 'Operaciones');
        $unidades->agregar(2, 'Ventas');
        $unidades->agregar(3, 'Finanzas');
        $unidades->agregar(4, 'Tecnología', 'Inactivo');

        $escenario = new self($unidades, new ColaboradoresFalso, new EquipoFalso);

        app()->instance(UnidadesPort::class, $unidades);
        app()->instance(ColaboradoresPort::class, $escenario->colaboradores);
        app()->instance(EquipoPort::class, $escenario->equipo);
        app()->instance(EmpresaActualPort::class, new EmpresaActualFalsa);

        return self::$actual = $escenario;
    }

    /** Usuario sin permisos de gestión, con ficha y unidad actual. Queda autenticado. */
    public function actuarComoColaborador(?int $unidadId): UserModel
    {
        $usuario = UserModel::factory()->create();
        Sanctum::actingAs($usuario, ['user']);

        $colaboradorId = 1000 + (int) $usuario->id;
        $this->colaboradores->cuentas[(int) $usuario->id] = $colaboradorId;
        $this->colaboradores->unidades[$colaboradorId] = $unidadId;

        return $usuario;
    }

    /** Usuario con equipo a cargo (líder). Queda autenticado. */
    public function actuarComoLider(int $unidadId = 1): UserModel
    {
        $usuario = $this->actuarComoColaborador($unidadId);
        $this->equipo->equipos[1000 + (int) $usuario->id] = [2000, 2001];

        return $usuario;
    }
}
