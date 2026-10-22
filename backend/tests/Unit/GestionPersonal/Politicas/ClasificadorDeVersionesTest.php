<?php

use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\Services\ClasificadorDeVersiones;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeVersion;

function instante(string $texto): DateTimeImmutable
{
    return new DateTimeImmutable($texto, new DateTimeZone('UTC'));
}

function versionDePrueba(int $id, ?int $numero, ?string $inicio, array $extra = []): VersionDePolitica
{
    $creada = instante('2026-01-01 00:00:00');

    return new VersionDePolitica(
        id: $id, tenantId: 1, politicaId: 1, numero: $numero, descripcion: 'x', parametros: [], baseVersionId: null,
        version: 1, createdBy: 1, updatedBy: 1, createdAt: $creada, updatedAt: $creada,
        descartadaEn: $extra['descartada'] ?? null,
        publicadaEn: $numero === null ? null : $creada,
        inicioVigencia: $inicio === null ? null : instante($inicio),
        retiradaEn: $extra['retirada'] ?? null,
    );
}

beforeEach(fn () => $this->clasificador = new ClasificadorDeVersiones);

it('una programada pasa a vigente y la anterior a sustituida al llegar el inicio', function () {
    $v1 = versionDePrueba(1, 1, '2026-02-01 00:00:00');
    $v2 = versionDePrueba(2, 2, '2026-06-01 00:00:00');

    $antes = $this->clasificador->estados([$v1, $v2], instante('2026-05-31 23:59:59'));
    $despues = $this->clasificador->estados([$v1, $v2], instante('2026-06-01 00:00:00'));

    expect($antes[1])->toBe(EstadoDeVersion::Vigente)->and($antes[2])->toBe(EstadoDeVersion::Programada)
        ->and($despues[1])->toBe(EstadoDeVersion::Sustituida)->and($despues[2])->toBe(EstadoDeVersion::Vigente);
});

it('una versión retirada antes de iniciar queda Retirada y la anterior sigue vigente', function () {
    $v1 = versionDePrueba(1, 1, '2026-02-01 00:00:00');
    $v2 = versionDePrueba(2, 2, '2026-06-01 00:00:00', ['retirada' => instante('2026-03-01 00:00:00')]);

    $estados = $this->clasificador->estados([$v1, $v2], instante('2026-07-01 00:00:00'));

    expect($estados[1])->toBe(EstadoDeVersion::Vigente)->and($estados[2])->toBe(EstadoDeVersion::Retirada);
});

it('con el mismo inicio gana la de mayor número', function () {
    $v1 = versionDePrueba(1, 1, '2026-02-01 00:00:00');
    $v2 = versionDePrueba(2, 2, '2026-02-01 00:00:00');

    $estados = $this->clasificador->estados([$v1, $v2], instante('2026-02-01 00:00:00'));

    expect($estados[2])->toBe(EstadoDeVersion::Vigente)->and($estados[1])->toBe(EstadoDeVersion::Sustituida);
});

it('sin versiones iniciadas no hay vigente y los borradores y descartados se distinguen', function () {
    $programada = versionDePrueba(1, 1, '2026-06-01 00:00:00');
    $borrador = versionDePrueba(2, null, null);
    $descartado = versionDePrueba(3, null, null, ['descartada' => instante('2026-01-02 00:00:00')]);

    $estados = $this->clasificador->estados([$programada, $borrador, $descartado], instante('2026-05-01 00:00:00'));

    expect($estados[1])->toBe(EstadoDeVersion::Programada)->and($estados[2])->toBe(EstadoDeVersion::Borrador)
        ->and($estados)->not->toHaveKey(3)
        ->and($this->clasificador->vigente([$programada], instante('2026-05-01 00:00:00')))->toBeNull()
        ->and($this->clasificador->programada([$programada, $borrador], instante('2026-05-01 00:00:00'))?->id)->toBe(1)
        ->and($this->clasificador->borrador([$programada, $borrador, $descartado])?->id)->toBe(2);
});

it('siempre hay como máximo una vigente', function () {
    $versiones = [
        versionDePrueba(1, 1, '2026-01-10 00:00:00'), versionDePrueba(2, 2, '2026-02-10 00:00:00'),
        versionDePrueba(3, 3, '2026-03-10 00:00:00'), versionDePrueba(4, 4, '2026-09-10 00:00:00'),
    ];

    $estados = $this->clasificador->estados($versiones, instante('2026-04-01 00:00:00'));

    expect(array_filter($estados, fn ($e) => $e === EstadoDeVersion::Vigente))->toHaveCount(1)
        ->and($estados[3])->toBe(EstadoDeVersion::Vigente)->and($estados[4])->toBe(EstadoDeVersion::Programada);
});
