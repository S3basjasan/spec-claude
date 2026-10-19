<?php

use App\Modules\Desarrollo\Domain\Repositories\ReportesRepositoryInterface;
use App\Modules\Desarrollo\Presentation\Facades\DesarrolloFacade;

beforeEach(function () {
    $this->falsos = actividadFalsos(['hoy' => '2026-10-17']);
    departamentosActuales([]);
});

it('todos los endpoints exigen sesión', function (string $metodo, string $url) {
    $this->json($metodo, $url, [])->assertUnauthorized();
})->with([
    ['GET', '/api/reportes/dashboard'], ['GET', '/api/reportes/detalle'], ['GET', '/api/reportes/actividades/1/satisfaccion'],
    ['GET', '/api/reportes/seguimiento'], ['GET', '/api/reportes/exportar'], ['GET', '/api/reportes/impactos'], ['PUT', '/api/reportes/impactos/1'],
    ['GET', '/api/reportes/mi-progreso'], ['POST', '/api/reportes/encuestas'], ['POST', '/api/reportes/seguimientos'],
]);

it('los endpoints del administrador rechazan al colaborador', function (string $metodo, string $url) {
    actuarComoUsuarioDeTenant();

    $this->json($metodo, $url, [])->assertForbidden();
})->with([
    ['GET', '/api/reportes/dashboard'], ['GET', '/api/reportes/detalle'], ['GET', '/api/reportes/actividades/1/satisfaccion'],
    ['GET', '/api/reportes/seguimiento'], ['GET', '/api/reportes/exportar'], ['GET', '/api/reportes/impactos'], ['PUT', '/api/reportes/impactos/1'],
]);

it('no existen rutas para modificar o eliminar encuestas ni seguimientos', function (string $metodo, string $url) {
    actuarComoAdminDeTenant();

    expect($this->json($metodo, $url, [])->status())->toBeIn([404, 405]);
})->with([
    ['PUT', '/api/reportes/encuestas'], ['PATCH', '/api/reportes/encuestas/1'], ['DELETE', '/api/reportes/encuestas/1'],
    ['PUT', '/api/reportes/seguimientos'], ['PATCH', '/api/reportes/seguimientos/1'], ['DELETE', '/api/reportes/seguimientos/1'],
]);

it('el repositorio no ofrece operaciones de actualización ni de eliminación de encuestas y seguimientos', function () {
    $metodos = array_map(fn (ReflectionMethod $m) => $m->getName(), (new ReflectionClass(ReportesRepositoryInterface::class))->getMethods());

    foreach ($metodos as $metodo) {
        expect($metodo)->not->toMatch('/^(actualizar|eliminar|borrar|modificar|update|delete)/i');
    }
});

it('DesarrolloFacade no expone métodos de reportes a otros módulos', function () {
    $facade = (string) (new ReflectionClass(DesarrolloFacade::class))->getDocComment();

    expect($facade)->not->toContain('Encuesta')->not->toContain('Seguimiento')->not->toContain('Impacto')->not->toContain('Dashboard');
});
