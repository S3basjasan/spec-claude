<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\UnidadModel;
use Illuminate\Support\Facades\Event;

/**
 * Simula un fallo al escribir el historial. Se limpia en afterEach con `Event::forget`, nunca con
 * `flushEventListeners` (borraría también los eventos de inmutabilidad del modelo).
 */
function fallarAlEscribirElHistorial(): void
{
    AuditoriaCambioModel::creating(function () {
        throw new RuntimeException('Fallo simulado al escribir el historial.');
    });
}

afterEach(function () {
    Event::forget('eloquent.creating: '.AuditoriaCambioModel::class);
});

it('si falla el historial al crear, no queda ninguna unidad ni código consumido', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();
    fallarAlEscribirElHistorial();

    $this->withoutExceptionHandling();
    expect(fn () => $this->postJson('/api/unidades', datosUnidad($raiz->id)))->toThrow(RuntimeException::class);

    expect(UnidadModel::query()->count())->toBe(1)->and(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('si falla el historial al actualizar, se revierte el cambio de la unidad y el aumento de versión', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    fallarAlEscribirElHistorial();

    $this->withoutExceptionHandling();
    expect(fn () => $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['nombre' => 'Personas'])))->toThrow(RuntimeException::class);

    $talento = $a['talento']->fresh();
    expect($talento->nombre)->toBe('Talento humano')->and($talento->version)->toBe(1)->and($talento->updated_by)->toBeNull();
});

it('si falla el historial al cambiar el estado, la unidad conserva su estado y su fecha de supresión', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();
    fallarAlEscribirElHistorial();

    $this->withoutExceptionHandling();
    expect(fn () => $this->patchJson("/api/unidades/{$a['reclutamiento']->id}/estado", ['estado' => 'Inactiva']))->toThrow(RuntimeException::class);

    $reclutamiento = $a['reclutamiento']->fresh();
    expect($reclutamiento->estado)->toBe('Activa')->and($reclutamiento->fecha_supresion)->toBeNull();
});

it('una validación del árbol que falla después de aumentar la versión revierte también el aumento', function () {
    actuarComoAdmin();
    $a = arbolDeEjemplo();

    $this->putJson("/api/unidades/{$a['talento']->id}", cuerpoDeUnidad($a['talento'], ['unidad_padre_id' => $a['reclutamiento']->id]))
        ->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);

    $talento = $a['talento']->fresh();
    expect($talento->version)->toBe(1)->and($talento->updated_by)->toBeNull()->and($talento->unidad_padre_id)->toBe($a['raiz']->id)
        ->and(AuditoriaCambioModel::query()->count())->toBe(0);
});

it('una creación rechazada por el árbol tampoco deja unidad ni historial', function () {
    actuarComoAdmin();
    $raiz = UnidadModel::factory()->raiz()->create();
    $inactiva = UnidadModel::factory()->conPadre($raiz)->inactiva()->create();

    $this->postJson('/api/unidades', datosUnidad($inactiva->id))->assertUnprocessable()->assertJsonValidationErrors(['unidad_padre_id']);

    expect(UnidadModel::query()->count())->toBe(2)->and(AuditoriaCambioModel::query()->count())->toBe(0);
});
