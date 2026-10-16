<?php

use App\Modules\GestionPersonal\Application\Ports\CatalogoDeCategoriasPort;
use App\Modules\GestionPersonal\Domain\Exceptions\CategoriaInactiva;
use App\Modules\GestionPersonal\Domain\Exceptions\InformacionNoDisponible;
use App\Modules\GestionPersonal\Domain\ValueObjects\Lectura;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\CategoriaDocumentalModel;
use App\Modules\GestionPersonal\Presentation\Facades\PersonalFacade;
use Illuminate\Support\Facades\DB;

it('entrega solo las activas del tenant ordenadas por nombre', function () {
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Zeta']);
    CategoriaDocumentalModel::factory()->create(['nombre' => 'Beta']);
    CategoriaDocumentalModel::factory()->inactiva()->create(['nombre' => 'Antigua']);
    CategoriaDocumentalModel::factory()->create(['tenant_id' => 2, 'nombre' => 'Ajena']);

    $activas = PersonalFacade::getCategoriasActivas(1);

    expect(array_column($activas, 'nombre'))->toBe(['Beta', 'Zeta'])
        ->and(array_keys($activas[0]))->toBe(['id', 'nombre']);
});

it('resuelve el nombre vigente de cualquier estado, omite ajenos y usa una sola consulta', function () {
    $activa = CategoriaDocumentalModel::factory()->create(['nombre' => 'Contratos']);
    $inactiva = CategoriaDocumentalModel::factory()->inactiva()->create(['nombre' => 'Antigua']);
    $ajena = CategoriaDocumentalModel::factory()->create(['tenant_id' => 2]);
    $activa->update(['nombre' => 'Contratos laborales', 'nombre_clave' => 'contratos laborales']);

    DB::enableQueryLog();
    $datos = PersonalFacade::getCategoriasPorIds(1, [$activa->id, $inactiva->id, $ajena->id, 999999]);
    $consultas = count(DB::getQueryLog());

    expect($consultas)->toBe(1)
        ->and(array_keys($datos))->toEqualCanonicalizing([$activa->id, $inactiva->id])
        ->and($datos[$activa->id]['nombre'])->toBe('Contratos laborales')
        ->and($datos[$inactiva->id])->toBe(['id' => $inactiva->id, 'nombre' => 'Antigua', 'estado' => 'Inactiva']);
});

it('la fachada degrada con la excepción del módulo si la lectura falla', function () {
    app()->bind(CatalogoDeCategoriasPort::class, fn () => new class implements CatalogoDeCategoriasPort
    {
        public function activas(int $tenantId): Lectura
        {
            return Lectura::noDisponible();
        }

        public function porIds(int $tenantId, array $ids): Lectura
        {
            return Lectura::noDisponible();
        }

        public function confirmarActiva(int $tenantId, int $categoriaId): void {}
    });
    app()->forgetInstance('personal-facade');
    PersonalFacade::clearResolvedInstance('personal-facade');

    expect(fn () => PersonalFacade::getCategoriasActivas(1))->toThrow(InformacionNoDisponible::class)
        ->and(fn () => PersonalFacade::getCategoriasPorIds(1, [1]))->toThrow(InformacionNoDisponible::class);
});

it('confirma una categoría activa sin modificar versión ni auditoría', function () {
    $categoria = CategoriaDocumentalModel::factory()->create(['updated_at' => '2024-01-01 10:00:00']);

    DB::transaction(fn () => PersonalFacade::confirmarCategoriaActiva(1, $categoria->id));

    $fresca = $categoria->fresh();
    expect($fresca->version)->toBe(1)->and($fresca->updated_at->format('Y-m-d H:i:s'))->toBe('2024-01-01 10:00:00');
});

it('rechaza la categoría inactiva, inexistente o de otro tenant con CategoriaInactiva', function () {
    $inactiva = CategoriaDocumentalModel::factory()->inactiva()->create();
    $ajena = CategoriaDocumentalModel::factory()->create(['tenant_id' => 2]);

    expect(fn () => PersonalFacade::confirmarCategoriaActiva(1, $inactiva->id))->toThrow(CategoriaInactiva::class)
        ->and(fn () => PersonalFacade::confirmarCategoriaActiva(1, $ajena->id))->toThrow(CategoriaInactiva::class)
        ->and(fn () => PersonalFacade::confirmarCategoriaActiva(1, 999999))->toThrow(CategoriaInactiva::class);
});

it('una carga cuya categoría se desactivó antes de publicar se rechaza sin reasignarla', function () {
    actuarComoAdminDeTenant();
    $categoria = CategoriaDocumentalModel::factory()->create();
    $this->patchJson(urlCategoria($categoria->id, '/estado'), ['estado' => 'Inactiva', 'version' => 1])->assertOk();

    expect(fn () => DB::transaction(fn () => PersonalFacade::confirmarCategoriaActiva(1, $categoria->id)))->toThrow(CategoriaInactiva::class);
    expect($categoria->fresh()->estado)->toBe('Inactiva');
});
