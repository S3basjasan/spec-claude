<?php

use App\Modules\Desarrollo\Application\DTO\CertificadoDTO;
use App\Modules\Desarrollo\Application\Ports\CertificadoPdfInterface;
use App\Modules\Desarrollo\Application\Ports\NombreDeColaboradorInterface;
use App\Modules\Desarrollo\Infrastructure\Adapters\CertificadoPdfDompdf;
use App\Modules\Estructura\Infrastructure\Persistence\Eloquent\Models\CompetenciaModel;

function pdfFalso(): void
{
    app()->instance(CertificadoPdfInterface::class, new class implements CertificadoPdfInterface
    {
        public function renderizar(CertificadoDTO $certificado): string
        {
            $GLOBALS['certificado_capturado'] = $certificado;

            return '%PDF-1.4 falso %%EOF';
        }
    });
}

it('descarga el PDF de un contenido completado con los cinco datos exigidos', function () {
    $competencia = CompetenciaModel::factory()->create(['nombre' => 'Liderazgo']);
    $micro = microDe(['titulo' => 'Cómo liderar', 'estado' => 'Publicado', 'duracion_minutos' => 25], [$competencia->id]);
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($micro->id, $ficha, 'Completado');

    app()->instance(NombreDeColaboradorInterface::class, new class implements NombreDeColaboradorInterface
    {
        public function nombreDe(int $tenantId, int $colaboradorId): ?string
        {
            return 'Ana Pérez';
        }
    });
    pdfFalso();

    $respuesta = $this->get("/api/microcontenidos/{$micro->id}/certificado")->assertOk();

    /** @var CertificadoDTO $c */
    $c = $GLOBALS['certificado_capturado'];
    expect($respuesta->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($respuesta->headers->get('Content-Disposition'))->toContain("certificado-{$micro->codigo}.pdf")
        ->and($c->colaborador)->toBe('Ana Pérez')
        ->and($c->titulo)->toBe('Cómo liderar')
        ->and($c->competencias)->toBe(['Liderazgo'])
        ->and($c->duracionMinutos)->toBe(25)
        ->and($c->fechaFinalizacion)->not->toBe('');
});

it('también se emite si el contenido se archivó después de completarlo', function () {
    pdfFalso();
    $micro = microDe(['estado' => 'Archivado']);
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($micro->id, $ficha, 'Completado');

    $this->get("/api/microcontenidos/{$micro->id}/certificado")->assertOk();
});

it('el adaptador de dompdf genera un PDF válido con datos escapados', function () {
    $pdf = (new CertificadoPdfDompdf)->renderizar(new CertificadoDTO('MICRO-0001', 'Ana <b>Pérez</b>', 'Título & más', ['Liderazgo'], 25, '2026-10-16T10:00:00+00:00'));

    expect($pdf)->toStartWith('%PDF-')->and(strlen($pdf))->toBeGreaterThan(500);
})->skip(! class_exists(Dompdf\Dompdf::class), 'dompdf no está instalado en este entorno (composer install).');

it('devuelve 404 si no lo completó, no existe o es de otro colaborador', function () {
    $enProgreso = microDe(['estado' => 'Publicado']);
    $ajeno = microDe(['estado' => 'Publicado']);
    progresoMicro($ajeno->id, 99999, 'Completado');
    [, $ficha] = colaboradorConCuentaDePlan();
    progresoMicro($enProgreso->id, $ficha, 'EnProgreso');

    $this->getJson("/api/microcontenidos/{$enProgreso->id}/certificado")->assertNotFound();
    $this->getJson("/api/microcontenidos/{$ajeno->id}/certificado")->assertNotFound();
    $this->getJson('/api/microcontenidos/9999/certificado')->assertNotFound();
});

it('exige sesión y rechaza al administrador', function () {
    $micro = microDe(['estado' => 'Publicado']);
    $this->getJson("/api/microcontenidos/{$micro->id}/certificado")->assertUnauthorized();

    actuarComoAdmin();
    $this->getJson("/api/microcontenidos/{$micro->id}/certificado")->assertForbidden();
});
