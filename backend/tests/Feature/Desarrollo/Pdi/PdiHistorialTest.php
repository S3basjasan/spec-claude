<?php

use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\Desarrollo\Infrastructure\Persistence\Eloquent\Models\PdiModel;

it('registra creación, acuerdo, aceptación, devolución, edición, responsable, vínculo, avance, hito y cierre con usuario, fecha y valores', function () {
    $admin = pdiComoAdmin();
    $id = $this->postJson('/api/pdi', pdiCuerpo($admin->id))->json('data.id');
    [, $ficha] = colaboradorConCuentaDePlan();
    PdiModel::query()->whereKey($id)->update(['colaborador_id' => $ficha]);
    actuarComoAdminDeTenant();

    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 1])->assertOk();
    actuarComoColaboradorDelPlan($ficha);
    $this->postJson("/api/pdi/{$id}/devolver-colaborador", ['version' => 2, 'comentario' => 'Quiero ajustar el periodo.'])->assertOk();
    actuarComoAdminDeTenant();
    $this->putJson("/api/pdi/{$id}", pdiCuerpo($admin->id, ['colaborador_id' => $ficha]) + ['version' => 3])->assertOk();
    $this->postJson("/api/pdi/{$id}/acuerdo", ['version' => 4])->assertOk();
    actuarComoColaboradorDelPlan($ficha);
    $this->postJson("/api/pdi/{$id}/aceptar", ['version' => 5])->assertOk();
    actuarComoAdminDeTenant();
    $detalle = $this->getJson("/api/pdi/{$id}")->json('data');
    $this->patchJson('/api/pdi-acciones/'.$detalle['compromisos'][0]['acciones'][0]['id'].'/avance', ['estado' => 'Cumplida'])->assertOk();
    $this->patchJson('/api/pdi-hitos/'.$detalle['hitos'][0]['id'], ['estado' => 'Realizado'])->assertOk();
    $this->postJson("/api/pdi/{$id}/cerrar", ['version' => 6, 'resultados' => [['compromiso_id' => $detalle['compromisos'][0]['id'], 'resultado' => 'Cerrada']]])->assertOk();

    $r = $this->getJson("/api/pdi/{$id}/historial?per_page=50")->assertOk();
    $tipos = collect($r->json('data'))->pluck('tipo')->all();

    expect($tipos)->toContain('Creacion', 'Edicion', 'Acuerdo', 'Aceptacion', 'Devolucion', 'Estado', 'Avance', 'Hito', 'Cierre')
        ->and($r->json('data.0.tipo'))->toBe('Cierre')
        ->and($r->json('data.0'))->toHaveKeys(['id', 'fecha', 'usuario', 'tipo', 'tipo_etiqueta', 'valor_anterior', 'valor_nuevo', 'motivo'])
        ->and(collect($r->json('data'))->firstWhere('tipo', 'Devolucion')['motivo'])->toBe('Quiero ajustar el periodo.')
        ->and($r->json('meta.total'))->toBe(count($tipos));
});

it('pagina el historial y es de solo lectura', function () {
    $admin = pdiComoAdmin();
    $plan = pdiEnBd(['responsable' => (int) $admin->id]);
    $this->patchJson("/api/pdi/{$plan->id}/responsable", ['responsable_id' => (int) $admin->id, 'version' => 1])->assertOk();

    $this->getJson("/api/pdi/{$plan->id}/historial?per_page=1&page=2")->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.tipo', 'Creacion');
    $this->putJson("/api/pdi/{$plan->id}/historial", [])->assertStatus(405);
    $this->deleteJson("/api/pdi/{$plan->id}/historial")->assertStatus(405);
    $this->getJson('/api/pdi/999/historial')->assertNotFound();
});

it('el colaborador solo ve el historial de sus planes visibles', function () {
    pdiFalsos();
    [, $ficha] = colaboradorConCuentaDePlan();
    $propio = pdiEnBd(['colaborador' => $ficha, 'estado' => EstadoDePdi::Acordado]);
    $oculto = pdiEnBd(['colaborador' => $ficha, 'tipo' => TipoDePdi::Carrera, 'puesto' => 2]);
    $ajeno = pdiEnBd(['colaborador' => $ficha + 40, 'estado' => EstadoDePdi::Acordado]);

    $this->getJson("/api/pdi/{$propio->id}/historial")->assertOk();
    $this->getJson("/api/pdi/{$oculto->id}/historial")->assertNotFound();
    $this->getJson("/api/pdi/{$ajeno->id}/historial")->assertNotFound();
});
