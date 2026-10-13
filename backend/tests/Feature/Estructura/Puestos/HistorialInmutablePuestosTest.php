<?php

use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\AuditoriaCambioModel;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\PuestoModel;
use Illuminate\Support\Facades\Route;

it('bajo /api/puestos solo existen las seis operaciones del contrato: ninguna consulta, modifica ni borra el historial', function () {
    $rutas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($ruta) => str_starts_with($ruta->uri(), 'api/puestos'))
        ->flatMap(fn ($ruta) => collect($ruta->methods())->reject(fn (string $metodo) => $metodo === 'HEAD')->map(fn (string $metodo) => "$metodo /{$ruta->uri()}"))
        ->sort()
        ->values()
        ->all();

    expect($rutas)->toBe([
        'GET /api/puestos',
        'GET /api/puestos/competencias-disponibles',
        'GET /api/puestos/{puestoId}',
        'PATCH /api/puestos/{puestoId}/estado',
        'POST /api/puestos',
        'PUT /api/puestos/{puestoId}',
    ]);
});

it('DELETE no existe para los puestos, ni siquiera para el Administrador', function () {
    $puesto = puestoDe();
    actuarComoAdmin();

    expect($this->deleteJson("/api/puestos/{$puesto->id}")->status())->toBeIn([404, 405])
        ->and($this->deleteJson('/api/puestos')->status())->toBeIn([404, 405])
        ->and(PuestoModel::query()->count())->toBe(1);
});

it('las filas del historial no se pueden modificar ni borrar desde la aplicación', function () {
    $fila = AuditoriaCambioModel::query()->create([
        'entidad' => 'puesto',
        'entidad_id' => 1,
        'campo' => 'nombre',
        'valor_anterior' => 'A',
        'valor_nuevo' => 'B',
        'user_id' => null,
    ]);

    expect(fn () => $fila->update(['valor_nuevo' => 'C']))->toThrow(LogicException::class, 'El historial de cambios no se puede modificar.');
    expect(fn () => $fila->delete())->toThrow(LogicException::class, 'El historial de cambios no se puede borrar.');
    expect($fila->fresh()->valor_nuevo)->toBe('B')
        ->and(AuditoriaCambioModel::query()->count())->toBe(1);
});

it('el historial sobrevive a las ediciones y a los cambios de estado de un puesto', function () {
    $puesto = puestoDe(['nombre' => 'Analista']);
    actuarComoAdmin();
    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Inactivo'])->assertOk();
    $antes = AuditoriaCambioModel::query()->count();

    $this->putJson("/api/puestos/{$puesto->id}", cuerpoDelPuesto($puesto, ['mision' => 'Otra misión.']))->assertOk();
    $this->patchJson("/api/puestos/{$puesto->id}/estado", ['estado' => 'Activo'])->assertOk();

    expect(AuditoriaCambioModel::query()->count())->toBeGreaterThan($antes)
        ->and(PuestoModel::query()->count())->toBe(1);
});
