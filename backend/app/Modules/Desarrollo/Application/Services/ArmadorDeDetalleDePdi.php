<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\AprobadoresInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeUsuarioInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\Services\CalculadorDeAvance;
use App\Modules\Desarrollo\Domain\Services\HitosVencidos;
use App\Modules\Desarrollo\Domain\Services\ReglasDeCompletitudDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/** Compone la respuesta de un plan: nombres en lote, estado resuelto de las acciones, avance y botones permitidos. */
final class ArmadorDeDetalleDePdi
{
    private const NO_DISPONIBLE = 'No disponible';

    public function __construct(
        private readonly NombresDeReferenciaInterface $nombres,
        private readonly NombresDeUsuarioInterface $usuarios,
        private readonly DepartamentoDeColaboradorInterface $departamentoDe,
        private readonly DepartamentosInterface $departamentos,
        private readonly AprobadoresInterface $aprobadores,
        private readonly ResolvedorDeEstadoDeAcciones $resolvedor,
        private readonly CalculadorDeAvance $avance,
        private readonly HitosVencidos $vencidos,
        private readonly ReglasDeCompletitudDePdi $completitud,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function detalle(Pdi $pdi, bool $esAdministrador, int $usuarioId): array
    {
        $hoy = $this->reloj->hoy($usuarioId);
        $estados = $this->resolvedor->resolver($pdi, $hoy);
        $referencias = $this->referencias($pdi);
        $responsableActivo = $this->responsableActivo($pdi);

        return [
            'id' => $pdi->id,
            'codigo' => $pdi->codigo,
            'colaborador' => [
                'id' => $pdi->colaboradorId,
                'nombre' => $referencias['colaboradores'][$pdi->colaboradorId] ?? self::NO_DISPONIBLE,
                'departamento' => $referencias['departamento'],
            ],
            'tipo' => $pdi->tipo->value,
            'estado' => $pdi->estado->value,
            'objetivo' => $pdi->objetivo,
            'puesto_objetivo' => $pdi->puestoObjetivoId === null ? null : [
                'id' => $pdi->puestoObjetivoId,
                'nombre' => $referencias['puestos'][$pdi->puestoObjetivoId]['nombre'] ?? self::NO_DISPONIBLE,
            ],
            'responsable' => [
                'id' => $pdi->responsableId,
                'nombre' => $referencias['usuarios'][$pdi->responsableId] ?? self::NO_DISPONIBLE,
                'sin_permiso' => ! $responsableActivo,
            ],
            'fecha_inicio' => $pdi->periodo->inicio->format('Y-m-d'),
            'fecha_fin' => $pdi->periodo->fin->format('Y-m-d'),
            'avance' => $this->avance->calcular(array_map(fn (array $r) => $r['estado'], array_values($estados))),
            'hitos_vencidos' => $this->vencidos->contar($pdi->hitos, $hoy),
            'version' => $pdi->version,
            'origen' => $this->origen($pdi),
            'acuerdo_responsable' => $pdi->acuerdoResponsableAt === null ? null : [
                'fecha' => $this->fecha($pdi->acuerdoResponsableAt),
                'usuario' => $referencias['usuarios'][(int) $pdi->acuerdoResponsableBy] ?? self::NO_DISPONIBLE,
            ],
            'aceptacion_colaborador' => $pdi->aceptacionColaboradorAt === null ? null : $this->fecha($pdi->aceptacionColaboradorAt),
            'comentario_colaborador' => $pdi->comentarioColaborador,
            'resultado' => $pdi->resultado?->value,
            'resultado_etiqueta' => $pdi->resultado?->etiqueta(),
            'conclusion' => $pdi->conclusion,
            'creado_por' => $referencias['usuarios'][$pdi->createdBy] ?? self::NO_DISPONIBLE,
            'creado_en' => $pdi->createdAt === null ? null : $this->fecha($pdi->createdAt),
            'cerrado_en' => $pdi->cerradoAt === null ? null : $this->fecha($pdi->cerradoAt),
            'compromisos' => array_map(fn (CompromisoDePdi $c) => $this->compromiso($c, $estados, $referencias), $pdi->compromisos),
            'hitos' => array_map(fn (HitoDePdi $h) => $this->hito($h, $hoy), $pdi->hitos),
            'completitud' => $esAdministrador ? $this->completitud->faltantes($pdi, $responsableActivo) : [],
            'acciones_permitidas' => $this->accionesPermitidas($pdi, $esAdministrador),
        ];
    }

    /**
     * Botones que el estado del plan y quién consulta habilitan (la autoridad sigue siendo el backend).
     *
     * @return array<string, bool>
     */
    public function accionesPermitidas(Pdi $pdi, bool $esAdministrador): array
    {
        $borrador = $pdi->estado === EstadoDePdi::Borrador;
        $acordado = $pdi->estado === EstadoDePdi::Acordado;
        $abierto = $pdi->estado !== EstadoDePdi::Cerrado;

        return [
            'editar' => $esAdministrador && $borrador,
            'acordar' => $esAdministrador && $borrador && ! $pdi->tieneAcuerdoDelResponsable(),
            'devolver' => $esAdministrador && $acordado,
            'avance' => $esAdministrador && $acordado,
            'vincular' => $esAdministrador && $abierto,
            'cerrar' => $esAdministrador && $acordado,
            'cambiar_responsable' => $esAdministrador && $abierto,
            'aceptar' => ! $esAdministrador && $borrador && $pdi->tieneAcuerdoDelResponsable(),
            'devolver_colaborador' => ! $esAdministrador && $borrador && $pdi->tieneAcuerdoDelResponsable(),
        ];
    }

    /** @return array{tipo: string, id: int}|null */
    private function origen(Pdi $pdi): ?array
    {
        if ($pdi->origenDnfId !== null) {
            return ['tipo' => 'dnf', 'id' => $pdi->origenDnfId];
        }

        return $pdi->origenAdecuacionId === null ? null : ['tipo' => 'adecuacion', 'id' => $pdi->origenAdecuacionId];
    }

    /**
     * @param  array<int, array<string, mixed>>  $estados
     * @param  array<string, mixed>  $referencias
     * @return array<string, mixed>
     */
    private function compromiso(CompromisoDePdi $c, array $estados, array $referencias): array
    {
        $elemento = null;
        if ($c->elementoId !== null) {
            $nombre = $c->dimension === DimensionDeCompromiso::Competencia
                ? ($referencias['competencias'][$c->elementoId]['nombre'] ?? self::NO_DISPONIBLE)
                : self::NO_DISPONIBLE;
            $elemento = ['id' => $c->elementoId, 'nombre' => $nombre];
        }

        return [
            'id' => $c->id,
            'orden' => $c->orden,
            'dimension' => $c->dimension->value,
            'tipo_elemento' => $c->tipoElemento,
            'elemento' => $elemento,
            'descripcion' => $c->descripcion,
            'nivel_actual' => $c->nivelActual,
            'nivel_objetivo' => $c->nivelObjetivo,
            'indicador' => $c->indicador,
            'fecha_inicio' => $c->fechaInicio->format('Y-m-d'),
            'fecha_fin' => $c->fechaFin->format('Y-m-d'),
            'resultado' => $c->resultado?->value,
            'acciones' => array_map(fn (AccionDePdi $a) => $this->accion($a, $estados[(int) $a->id] ?? null), $c->acciones),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $resuelta
     * @return array<string, mixed>
     */
    private function accion(AccionDePdi $a, ?array $resuelta): array
    {
        $estado = $resuelta['estado'] ?? $a->estado;

        return [
            'id' => $a->id,
            'orden' => $a->orden,
            'tipo' => $a->tipo->value,
            'descripcion' => $a->descripcion,
            'persona_cargo' => $a->personaCargo,
            'fecha_inicio' => $a->fechaInicio->format('Y-m-d'),
            'fecha_fin' => $a->fechaFin->format('Y-m-d'),
            'estado' => $estado->value,
            'estado_derivado' => (bool) ($resuelta['derivado'] ?? false),
            'vinculo' => $resuelta['vinculo'] ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function hito(HitoDePdi $h, DateTimeImmutable $hoy): array
    {
        return [
            'id' => $h->id,
            'fecha' => $h->fecha->format('Y-m-d'),
            'descripcion' => $h->descripcion,
            'estado' => $h->estado->value,
            'observaciones' => $h->observaciones,
            'vencido' => $h->estaVencido($hoy),
        ];
    }

    /** @return array{colaboradores: array<int, string>, puestos: array<int, array{codigo: string, nombre: string}>, competencias: array<int, array{codigo: string, nombre: string, activa: bool}>, usuarios: array<int, string>, departamento: string|null} */
    private function referencias(Pdi $pdi): array
    {
        $competenciaIds = [];
        foreach ($pdi->compromisos as $c) {
            if ($c->dimension === DimensionDeCompromiso::Competencia && $c->elementoId !== null) {
                $competenciaIds[] = $c->elementoId;
            }
        }
        $usuarioIds = array_values(array_unique(array_filter([$pdi->responsableId, $pdi->createdBy, $pdi->acuerdoResponsableBy])));

        return [
            'colaboradores' => $this->seguro(fn () => $this->nombres->colaboradores($pdi->tenantId, [$pdi->colaboradorId]), []),
            'puestos' => $pdi->puestoObjetivoId === null ? [] : $this->seguro(fn () => $this->nombres->puestos([$pdi->puestoObjetivoId]), []),
            'competencias' => $competenciaIds === [] ? [] : $this->seguro(fn () => $this->nombres->competencias(array_values(array_unique($competenciaIds))), []),
            'usuarios' => $this->seguro(fn () => $this->usuarios->nombresDe($usuarioIds), []),
            'departamento' => $this->departamento($pdi),
        ];
    }

    private function departamento(Pdi $pdi): ?string
    {
        return $this->seguro(function () use ($pdi) {
            $unidad = $this->departamentoDe->actuales($pdi->tenantId, [$pdi->colaboradorId], (new DateTimeImmutable('today'))->format('Y-m-d'))[$pdi->colaboradorId] ?? null;

            return $unidad === null ? null : ($this->departamentos->porIds([$unidad])[$unidad] ?? null);
        }, null);
    }

    private function responsableActivo(Pdi $pdi): bool
    {
        return $this->seguro(fn () => $this->aprobadores->esAdministradorActivo($pdi->tenantId, $pdi->responsableId), true);
    }

    private function fecha(DateTimeInterface $fecha): string
    {
        return $fecha->format('Y-m-d\TH:i:sP');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fuente
     * @param  T  $porDefecto
     * @return T
     */
    private function seguro(callable $fuente, mixed $porDefecto): mixed
    {
        try {
            return $fuente();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
