<?php

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\CompetenciasInterface;
use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Repositories\RepositorioDePdiInterface;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\ColaboradorCuentaModel;
use App\Modules\Shared\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Desarrollo\AprobadoresFalso;
use Tests\Support\Desarrollo\CompetenciasFalsas;

/**
 * Helpers de la feature 021 (Plan de desarrollo individual). Datos sintéticos; se apoyan en los de la feature 019
 * (colaboradores vigentes, puestos y departamentos). «Hoy» es 2026-06-15 salvo que se indique otra cosa.
 */

/**
 * Enlaza con dobles los puertos del PDI. Por defecto los colaboradores 10 a 13 tienen vínculo vigente (departamentos 1, 1,
 * 2 y 2), los puestos 1 a 5 están activos, las competencias 1 a 3 están activas y la 9 inactiva, y los administradores
 * son los indicados.
 *
 * @param  array<string, mixed>  $o  administradores (id => nombre), competencias, inactivas, unidades, hoy y las opciones de `rutasFalsos`
 */
function pdiFalsos(array $o = []): void
{
    rutasFalsos(['puestos' => $o['puestos'] ?? null, 'vigentes' => $o['vigentes'] ?? null, 'departamentos' => $o['departamentos'] ?? null, 'hoy' => $o['hoy'] ?? '2026-06-15']);
    departamentosActuales($o['unidades'] ?? [10 => 1, 11 => 1, 12 => 2, 13 => 2]);

    app()->instance(AprobadoresInterface::class, new AprobadoresFalso($o['administradores'] ?? [1 => 'Administrador']));
    app()->instance(CompetenciasInterface::class, new CompetenciasFalsas($o['competencias'] ?? [1, 2, 3], $o['inactivas'] ?? [9]));
}

/** Actúa como administrador del tenant 1 y enlaza los dobles con él como administrador activo. */
function pdiComoAdmin(array $o = []): UserModel
{
    $admin = actuarComoAdminDeTenant();
    pdiFalsos($o + ['administradores' => [(int) $admin->id => 'Administrador']]);

    return $admin;
}

/**
 * Cuerpo válido para crear un plan de Mejora del colaborador 10 con un compromiso de la competencia 1 (C → B), una
 * acción de tipo Curso y un hito. El periodo es 2026-07-01 a 2026-12-31.
 *
 * @param  array<string, mixed>  $cambios  se mezclan sobre el cuerpo (reemplazo de claves de primer nivel)
 * @return array<string, mixed>
 */
function pdiCuerpo(int $responsableId, array $cambios = []): array
{
    return array_replace([
        'colaborador_id' => 10,
        'tipo' => 'Mejora',
        'objetivo' => 'Cerrar las brechas de comunicación del puesto actual.',
        'responsable_id' => $responsableId,
        'fecha_inicio' => '2026-07-01',
        'fecha_fin' => '2026-12-31',
        'compromisos' => [[
            'dimension' => 'Competencia', 'tipo_elemento' => 'Competencia', 'elemento_id' => 1, 'nivel_actual' => 'C', 'nivel_objetivo' => 'B',
            'indicador' => 'Evaluación 360 ≥ B', 'fecha_inicio' => '2026-07-01', 'fecha_fin' => '2026-11-30',
            'acciones' => [[
                'tipo' => 'Curso', 'descripcion' => 'Curso de comunicación efectiva', 'persona_cargo' => null,
                'fecha_inicio' => '2026-07-15', 'fecha_fin' => '2026-09-30',
            ]],
        ]],
        'hitos' => [['fecha' => '2026-09-01', 'descripcion' => 'Primera revisión de avance']],
    ], $cambios);
}

/**
 * Guarda directamente un plan con el repositorio real.
 *
 * @param  array<string, mixed>  $o  colaborador, tipo, estado, responsable, puesto, acuerdo, aceptacion, acciones (lista de estados), hitos (lista de [fecha, estado]), vinculo, tenant, version
 */
function pdiEnBd(array $o = []): Pdi
{
    $tipo = $o['tipo'] ?? TipoDePdi::Mejora;
    $estado = $o['estado'] ?? EstadoDePdi::Borrador;
    $acciones = array_map(
        fn (EstadoDeAccionPdi|string $e, int $i) => new AccionDePdi(
            null, $i + 1, $o['tipoAccion'] ?? TipoDeAccionPdi::Curso, 'Acción '.($i + 1), null,
            new DateTimeImmutable('2026-07-15'), new DateTimeImmutable('2026-09-30'),
            $i === 0 ? ($o['vinculo'] ?? null) : null,
            $e instanceof EstadoDeAccionPdi ? $e : EstadoDeAccionPdi::from($e),
        ),
        $o['acciones'] ?? [EstadoDeAccionPdi::Pendiente],
        array_keys($o['acciones'] ?? [EstadoDeAccionPdi::Pendiente]),
    );
    $compromiso = new CompromisoDePdi(
        null, 1, DimensionDeCompromiso::Competencia, 'Competencia', 1, null, 'C', 'B', null,
        new DateTimeImmutable('2026-07-01'), new DateTimeImmutable('2026-11-30'), null, $acciones,
    );
    $hitos = array_map(
        fn (array $h) => new HitoDePdi(null, new DateTimeImmutable($h[0]), 'Revisión '.$h[0], EstadoDeHito::from($h[1] ?? 'Pendiente'), null),
        $o['hitos'] ?? [['2026-09-01']],
    );
    $conAcuerdo = $o['acuerdo'] ?? in_array($estado, [EstadoDePdi::Acordado, EstadoDePdi::Cerrado], true);
    $conAceptacion = $o['aceptacion'] ?? in_array($estado, [EstadoDePdi::Acordado, EstadoDePdi::Cerrado], true);

    $pdi = new Pdi(
        null, $o['tenant'] ?? 1, null, $o['colaborador'] ?? 10, $tipo, 'Objetivo de prueba',
        $tipo->requierePuestoObjetivo() ? ($o['puesto'] ?? 2) : null, null, null, $o['responsable'] ?? 1,
        new PeriodoDePdi(new DateTimeImmutable('2026-07-01'), new DateTimeImmutable($o['fin'] ?? '2026-12-31')),
        $estado, $o['version'] ?? 1,
        $conAcuerdo ? new DateTimeImmutable('2026-06-10 09:00:00') : null, $conAcuerdo ? ($o['responsable'] ?? 1) : null,
        $conAceptacion ? new DateTimeImmutable('2026-06-11 09:00:00') : null, null, null, null, null, null, 1, null,
        [$compromiso], $hitos,
    );

    return app(RepositorioDePdiInterface::class)->crear($pdi, 1);
}

/**
 * Actúa como el colaborador dueño de la ficha indicada: busca la cuenta ya creada para esa ficha y autentica a su usuario.
 */
function actuarComoColaboradorDelPlan(int $fichaId): void
{
    $cuenta = ColaboradorCuentaModel::query()->where('colaborador_id', $fichaId)->firstOrFail();
    Sanctum::actingAs(UserModel::query()->findOrFail($cuenta->user_id), ['user']);
}
