<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Application\Ports\DepartamentoDeColaboradorInterface;
use App\Modules\Desarrollo\Application\Ports\DepartamentosInterface;
use App\Modules\Desarrollo\Application\Ports\NombresDeReferenciaInterface;
use App\Modules\Desarrollo\Application\Ports\RelojInterface;
use App\Modules\Desarrollo\Domain\Entities\InscripcionARuta;
use App\Modules\Desarrollo\Domain\Entities\Ruta;
use App\Modules\Desarrollo\Domain\Repositories\RutasRepositoryInterface;
use Throwable;

/** Arma las respuestas de inscripción con nombres de colaborador, departamento actual y niveles legibles. */
final class ArmadorDeInscripciones
{
    public const NO_DISPONIBLE = 'No disponible';

    public const NOTA_ALCANZADO = 'Informativo: el cambio real de puesto lo ejecuta Gestión de Personal.';

    public function __construct(
        private readonly RutasRepositoryInterface $rutas,
        private readonly NombresDeReferenciaInterface $referencias,
        private readonly DepartamentoDeColaboradorInterface $departamentosActuales,
        private readonly DepartamentosInterface $departamentos,
        private readonly RelojInterface $reloj,
    ) {}

    /**
     * @param  list<InscripcionARuta>  $inscripciones
     * @return list<array<string, mixed>>
     */
    public function lista(int $tenantId, int $usuarioId, array $inscripciones): array
    {
        if ($inscripciones === []) {
            return [];
        }

        $colaboradorIds = array_values(array_unique(array_map(fn (InscripcionARuta $i) => $i->colaboradorId, $inscripciones)));
        $nombres = $this->nombres($tenantId, $colaboradorIds);
        $departamentoPorColaborador = $this->departamentosDe($tenantId, $usuarioId, $colaboradorIds);
        $nombresDeDepartamento = $this->nombresDeDepartamento(array_values(array_unique(array_filter($departamentoPorColaborador))));

        /** @var array<int, Ruta|null> $rutas */
        $rutas = [];
        $resultado = [];
        foreach ($inscripciones as $i) {
            $ruta = $rutas[$i->rutaId] ??= $this->rutas->obtener($tenantId, $i->rutaId);
            $departamentoId = $departamentoPorColaborador[$i->colaboradorId] ?? null;
            $resultado[] = $this->una($i, $ruta, $nombres[$i->colaboradorId] ?? self::NO_DISPONIBLE, $departamentoId === null ? null : ($nombresDeDepartamento[$departamentoId] ?? null));
        }

        return $resultado;
    }

    /** @return array<string, mixed> */
    public function detalle(int $tenantId, int $usuarioId, InscripcionARuta $inscripcion): array
    {
        return $this->lista($tenantId, $usuarioId, [$inscripcion])[0];
    }

    /** @return array<string, mixed> */
    private function una(InscripcionARuta $i, ?Ruta $ruta, string $nombre, ?string $departamento): array
    {
        return [
            'id' => $i->id,
            'ruta_id' => $i->rutaId,
            'ruta' => $ruta === null ? null : ['id' => $ruta->id, 'codigo' => $ruta->codigo(), 'nombre' => $ruta->nombre, 'estado' => $ruta->estado->value],
            'colaborador' => ['id' => $i->colaboradorId, 'nombre' => $nombre, 'departamento' => $departamento],
            'nivel_actual' => $this->nivel($i->nivelActualId, $ruta),
            'nivel_objetivo' => $this->nivel($i->nivelObjetivoId, $ruta),
            'estado' => $i->estado->value,
            'motivo' => $i->motivo,
            'created_at' => $i->creadoEn?->format(DATE_ATOM),
            'resuelta_at' => $i->resueltaEn?->format(DATE_ATOM),
        ];
    }

    /** @return array{id: int, orden: int, nombre: string}|null */
    private function nivel(?int $nivelId, ?Ruta $ruta): ?array
    {
        $nivel = $nivelId === null ? null : $ruta?->nivelPorId($nivelId);

        return $nivel === null ? null : ['id' => (int) $nivel->id, 'orden' => $nivel->orden, 'nombre' => $nivel->nombre];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombres(int $tenantId, array $ids): array
    {
        try {
            return $this->referencias->colaboradores($tenantId, $ids);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int|null>
     */
    private function departamentosDe(int $tenantId, int $usuarioId, array $ids): array
    {
        try {
            return $this->departamentosActuales->actuales($tenantId, $ids, $this->reloj->hoy($usuarioId)->format('Y-m-d'));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresDeDepartamento(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            return $this->departamentos->porIds($ids);
        } catch (Throwable) {
            return [];
        }
    }
}
