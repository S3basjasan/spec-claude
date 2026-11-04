<?php

use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\CategoriaReconocimientoModel;

function categoriaConUnidadesParaVisibilidad(string $nombre, ?array $unidades, bool $inactiva = false): CategoriaReconocimientoModel
{
    $fabrica = CategoriaReconocimientoModel::factory();

    if ($inactiva) {
        $fabrica = $fabrica->inactiva();
    }

    return $fabrica->create(['nombre' => $nombre, 'unidades_ids' => $unidades]);
}

function nombresVisiblesDelListadoPorRol($respuesta): array
{
    return collect($respuesta->json('data'))->pluck('nombre')->sort()->values()->all();
}

describe('colaborador', function () {
    it('ve solo las activas asignadas a su unidad, sin ver las unidades', function () {
        categoriaConUnidadesParaVisibilidad('Para operaciones', [1]);
        categoriaConUnidadesParaVisibilidad('Para ventas', [2]);
        categoriaConUnidadesParaVisibilidad('Para ambas', [1, 2]);
        categoriaConUnidadesParaVisibilidad('Inactiva de operaciones', [1], true);
        categoriaConUnidadesParaVisibilidad('Sin unidades', null);
        actuarComoColaboradorDeUnidad(1);

        $respuesta = $this->getJson('/api/compensaciones/categorias?estado=Todos')->assertOk();

        expect(nombresVisiblesDelListadoPorRol($respuesta))->toBe(['Para ambas', 'Para operaciones']);
        $respuesta->assertJsonMissingPath('data.0.unidades')->assertJsonMissingPath('data.0.aplica_a_todas')->assertJsonMissingPath('data.0.sin_unidades_activas');
    });

    it('no hereda categorías por la jerarquía: solo cuenta la asignación directa a su unidad', function () {
        categoriaConUnidadesParaVisibilidad('Solo de ventas', [2]);
        actuarComoColaboradorDeUnidad(1);

        $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');
    });

    it('recibe una lista vacía sin ficha, sin unidad o con la unidad inactiva', function () {
        categoriaConUnidadesParaVisibilidad('De todas', [1, 2, 3, 4]);

        actuarComoUsuario();
        $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');

        actuarComoColaboradorDeUnidad(null);
        $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');

        actuarComoColaboradorDeUnidad(4);
        $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');
    });

    it('vuelve a ver sus categorías si la unidad se reactiva y sigue la unidad actual al cambiar de unidad', function () {
        categoriaConUnidadesParaVisibilidad('Solo de tecnología', [4]);
        categoriaConUnidadesParaVisibilidad('Solo de ventas', [2]);
        $usuario = escenarioCompensaciones()->actuarComoColaborador(4);

        $this->getJson('/api/compensaciones/categorias')->assertOk()->assertJsonCount(0, 'data');

        escenarioCompensaciones()->unidades->unidades[4]['estado'] = 'Activo';
        expect(nombresVisiblesDelListadoPorRol($this->getJson('/api/compensaciones/categorias')))->toBe(['Solo de tecnología']);

        escenarioCompensaciones()->colaboradores->unidades[1000 + (int) $usuario->id] = 2;
        expect(nombresVisiblesDelListadoPorRol($this->getJson('/api/compensaciones/categorias')))->toBe(['Solo de ventas']);
    });

    it('responde 404 al detalle de una categoría fuera de su unidad o inactiva y 200 al de su unidad', function () {
        $propia = categoriaConUnidadesParaVisibilidad('Propia', [1]);
        $ajena = categoriaConUnidadesParaVisibilidad('Ajena', [2]);
        $inactiva = categoriaConUnidadesParaVisibilidad('Inactiva', [1], true);
        actuarComoColaboradorDeUnidad(1);

        $this->getJson("/api/compensaciones/categorias/{$ajena->id}")->assertNotFound();
        $this->getJson("/api/compensaciones/categorias/{$inactiva->id}")->assertNotFound();
        $this->getJson("/api/compensaciones/categorias/{$propia->id}")->assertOk()->assertJsonMissingPath('data.unidades');
    });

    it('no puede crear, editar ni cambiar el estado', function () {
        $categoria = categoriaConUnidadesParaVisibilidad('Propia', [1]);
        actuarComoColaboradorDeUnidad(1);

        $this->postJson('/api/compensaciones/categorias', datosCategoria())->assertForbidden();
        $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria))->assertForbidden();
        $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Inactivo', 'updated_at' => versionDeCategoria($categoria->id)])->assertForbidden();
    });
});

describe('líder', function () {
    it('ve todas las activas con sus unidades, aunque pida las inactivas', function () {
        categoriaConUnidadesParaVisibilidad('Para operaciones', [1]);
        categoriaConUnidadesParaVisibilidad('Para todas', [1, 2, 3]);
        categoriaConUnidadesParaVisibilidad('Inactiva', [1], true);
        categoriaConUnidadesParaVisibilidad('Sin unidades', null);
        actuarComoLiderDeEquipo(1);

        $respuesta = $this->getJson('/api/compensaciones/categorias?estado=Todos')->assertOk();

        expect(nombresVisiblesDelListadoPorRol($respuesta))->toBe(['Para operaciones', 'Para todas']);

        $porNombre = collect($respuesta->json('data'))->keyBy('nombre');
        expect($porNombre['Para operaciones']['aplica_a_todas'])->toBeFalse()
            ->and($porNombre['Para operaciones']['unidades'][0]['nombre'])->toBe('Operaciones')
            ->and($porNombre['Para todas']['aplica_a_todas'])->toBeTrue()
            ->and($porNombre['Para todas'])->not->toHaveKey('sin_unidades_activas');
    });

    it('consulta el detalle de una activa de otra unidad pero no de una inactiva', function () {
        $ajena = categoriaConUnidadesParaVisibilidad('Ajena', [2]);
        $inactiva = categoriaConUnidadesParaVisibilidad('Inactiva', [2], true);
        actuarComoLiderDeEquipo(1);

        $this->getJson("/api/compensaciones/categorias/{$ajena->id}")->assertOk()->assertJsonPath('data.unidades.0.nombre', 'Ventas');
        $this->getJson("/api/compensaciones/categorias/{$inactiva->id}")->assertNotFound();
    });

    it('no puede gestionar categorías por solicitud directa', function () {
        $categoria = categoriaConUnidadesParaVisibilidad('Propia', [1]);
        actuarComoLiderDeEquipo(1);

        $this->postJson('/api/compensaciones/categorias', datosCategoria())->assertForbidden();
        $this->putJson("/api/compensaciones/categorias/{$categoria->id}", cuerpoDeCategoria($categoria, ['unidades_ids' => [1, 2]]))->assertForbidden();
        $this->patchJson("/api/compensaciones/categorias/{$categoria->id}/estado", ['estado' => 'Inactivo', 'updated_at' => versionDeCategoria($categoria->id)])->assertForbidden();

        expect(CategoriaReconocimientoModel::query()->findOrFail($categoria->id)->unidades_ids)->toBe([1]);
    });
});

describe('Gestor', function () {
    it('ve todas, activas e inactivas, con sus unidades y las advertencias', function () {
        categoriaConUnidadesParaVisibilidad('Activa', [1, 2, 3]);
        categoriaConUnidadesParaVisibilidad('Inactiva', [2], true);
        categoriaConUnidadesParaVisibilidad('Solo unidad inactiva', [4]);
        categoriaConUnidadesParaVisibilidad('Sin unidades', null);
        actuarComoAdmin();

        $respuesta = $this->getJson('/api/compensaciones/categorias?estado=Todos')->assertOk();
        $porNombre = collect($respuesta->json('data'))->keyBy('nombre');

        expect($porNombre)->toHaveCount(4)
            ->and($porNombre['Activa']['aplica_a_todas'])->toBeTrue()
            ->and($porNombre['Activa']['sin_unidades_activas'])->toBeFalse()
            ->and($porNombre['Solo unidad inactiva']['unidades'][0]['activa'])->toBeFalse()
            ->and($porNombre['Solo unidad inactiva']['sin_unidades_activas'])->toBeTrue()
            ->and($porNombre['Sin unidades']['unidades'])->toBe([])
            ->and($porNombre['Sin unidades']['sin_unidades_activas'])->toBeTrue();
    });
});
