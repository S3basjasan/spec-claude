<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PosicionModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;

it('rechaza como padre la propia unidad o una dependiente, directa o indirecta', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $nieta = UnidadModel::factory()->conPadre($a['reclutamiento'])->create(['nombre' => 'Nieta']);

    foreach ([$a['talento'], $a['reclutamiento'], $nieta] as $padre) {
        $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['unidad_padre_id' => $padre->id]))
            ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    }
    expect($a['talento']->fresh()->unidad_padre_id)->toBe($a['raiz']->id)->and($a['talento']->fresh()->version)->toBe(1);
});

it('reubicar una unidad con rama la deja en su nuevo lugar con toda su rama y las posiciones conservan su unidad', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    posicionesDeUnidad($a['tecnologia'], 'Ocupada');
    posicionesDeUnidad($a['desarrollo'], 'Vacante', 2);

    $this->putJson("/api/unidades/{$a['tecnologia']->id}", cuerpoDeUnidad($a['tecnologia'], ['unidad_padre_id' => $a['talento']->id]))
        ->assertOk()->assertJsonPath('data.nivel', 2)->assertJsonPath('data.unidad_padre.id', $a['talento']->id);

    $this->getJson('/api/unidades')->assertJsonPath('data.*.nombre', ['Gerencia general', 'Talento humano', 'Tecnología', 'Desarrollo', 'Reclutamiento'])
        ->assertJsonPath('data.*.nivel', [0, 1, 2, 3, 2]);
    expect($a['desarrollo']->fresh()->unidad_padre_id)->toBe($a['tecnologia']->id)
        ->and(PosicionModel::query()->where('unidad_organizacional_id', $a['tecnologia']->id)->count())->toBe(1)
        ->and(PosicionModel::query()->where('unidad_organizacional_id', $a['desarrollo']->id)->count())->toBe(2);
});

it('reubicar registra el cambio solo en la unidad movida', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->putJson("/api/unidades/{$a['tecnologia']->id}", cuerpoDeUnidad($a['tecnologia'], ['unidad_padre_id' => $a['talento']->id]))->assertOk();

    expect(AuditoriaCambioModel::query()->pluck('entidad_id')->unique()->all())->toBe([$a['tecnologia']->id])
        ->and(AuditoriaCambioModel::query()->pluck('campo')->all())->toBe(['unidad_padre_id']);
});

it('una unidad activa no puede quedar bajo una inactiva', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['unidad_padre_id' => $inactiva->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    $this->postJson('/api/unidades', datosUnidad($inactiva->id))->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
});

it('dejar sin padre a una unidad activa con otra raíz activa se rechaza; una inactiva sí puede quedar sin padre', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $inactiva = UnidadModel::factory()->conPadre($a['raiz'])->inactiva()->create();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['unidad_padre_id' => null]))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
    expect($a['talento']->fresh()->unidad_padre_id)->toBe($a['raiz']->id);

    $this->putJson("/api/unidades/{$inactiva->id}", cuerpoDeUnidad($inactiva, ['unidad_padre_id' => null]))
        ->assertOk()->assertJsonPath('data.estado', 'Inactiva')->assertJsonPath('data.unidad_padre', null);
});

it('la única raíz activa puede editarse conservando su ausencia de padre', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->putJson("/api/unidades/{$a['raiz']->id}", cuerpoDeUnidad($a['raiz'], ['nombre' => 'Dirección general']))
        ->assertOk()->assertJsonPath('data.nombre', 'Dirección general')->assertJsonPath('data.unidad_padre', null);
});

it('dos cambios que juntos formarían un ciclo: el segundo se rechaza contra el estado vigente', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['unidad_padre_id' => $a['tecnologia']->id]))->assertOk();
    $this->putJson("/api/unidades/{$a['tecnologia']->id}", cuerpoDeUnidad($a['tecnologia'], ['unidad_padre_id' => $a['talento']->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);

    expect($a['tecnologia']->fresh()->unidad_padre_id)->toBe($a['raiz']->id);
});

it('reubicar bajo un padre que se acaba de desactivar se rechaza', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();
    $this->patchJson("/api/unidades/{$a['talento']->id}/estado", ['estado' => 'Inactiva'])->assertOk();

    $this->putJson("/api/unidades/{$a['tecnologia']->id}", cuerpoDeUnidad($a['tecnologia'], ['unidad_padre_id' => $a['talento']->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);
});
