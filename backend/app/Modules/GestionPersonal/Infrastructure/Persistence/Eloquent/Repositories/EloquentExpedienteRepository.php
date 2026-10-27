<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\Expediente;
use App\Modules\GestionPersonal\Domain\Exceptions\ConflictoDeUnicidad;
use App\Modules\GestionPersonal\Domain\Exceptions\EntrevistaCerrada;
use App\Modules\GestionPersonal\Domain\Exceptions\ExpedienteModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\SalidaYaFormalizada;
use App\Modules\GestionPersonal\Domain\Repositories\ExpedienteRepositoryInterface;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeEntrevista;
use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeSalida;
use App\Modules\GestionPersonal\Domain\ValueObjects\MotivoDeSalida;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class EloquentExpedienteRepository implements ExpedienteRepositoryInterface
{
    public function porId(int $tenantId, int $id): ?Expediente
    {
        $fila = DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->where('id', $id)->first();

        return $fila === null ? null : $this->hidratar($fila);
    }

    public function vigenteDeColaborador(int $tenantId, int $colaboradorId): ?Expediente
    {
        $fila = DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)
            ->where('estado', '<>', 'Cancelada')->orderByDesc('id')->first();

        return $fila === null ? null : $this->hidratar($fila);
    }

    public function porOperacion(int $tenantId, string $operacionId): ?array
    {
        $fila = DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->where('operacion_id', $operacionId)->first();

        return $fila === null ? null : ['expediente' => $this->hidratar($fila), 'hash' => (string) $fila->operacion_hash, 'actor' => (int) $fila->formalizado_por];
    }

    public function eventoPorOperacion(int $tenantId, string $operacionId): ?array
    {
        $fila = DB::table('offboarding_eventos')->where('tenant_id', $tenantId)->where('operacion_id', $operacionId)->first();

        return $fila === null ? null : [
            'expediente_id' => (int) $fila->expediente_id,
            'hash' => (string) $fila->operacion_hash,
            'actor_id' => $fila->actor_id === null ? null : (int) $fila->actor_id,
        ];
    }

    public function insertar(array $comun, array $tareasDePlantilla, array $documentoIds): Expediente
    {
        $ahora = now();
        $tenantId = (int) $comun['tenant_id'];

        try {
            $id = (int) DB::table('offboarding_expedientes')->insertGetId($comun + ['created_at' => $ahora, 'updated_at' => $ahora]);

            $orden = 0;
            foreach ($tareasDePlantilla as $tarea) {
                $orden++;
                DB::table('offboarding_tareas')->insert([
                    'tenant_id' => $tenantId, 'expediente_id' => $id, 'orden' => $orden, 'tipo' => 'Plantilla',
                    'nombre' => $tarea['nombre'], 'descripcion' => $tarea['descripcion'], 'exige_evidencia' => $tarea['exige_evidencia'] ? 1 : 0,
                ]);
            }
            DB::table('offboarding_tareas')->insert([
                'tenant_id' => $tenantId, 'expediente_id' => $id, 'orden' => $orden + 1, 'tipo' => 'Entrevista',
                'nombre' => 'Entrevista de salida',
                'descripcion' => 'Se ofrece al colaborador; debe quedar resuelta (completada o no realizada con motivo) para cerrar. Responder es voluntario.',
                'exige_evidencia' => 0,
            ]);
            DB::table('offboarding_entrevistas')->insert([
                'tenant_id' => $tenantId, 'expediente_id' => $id, 'colaborador_id' => $comun['colaborador_id'],
                'cuestionario_version' => 1, 'estado' => 'Pendiente', 'version' => 1, 'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
            foreach (array_values(array_unique($documentoIds)) as $documentoId) {
                DB::table('offboarding_documentos')->insert(['expediente_id' => $id, 'tarea_id' => 0, 'documento_id' => $documentoId, 'tenant_id' => $tenantId, 'created_at' => $ahora]);
            }
            $this->evento($tenantId, $id, 'Formalizada', (int) $comun['formalizado_por'], ['motivo' => $comun['motivo'], 'fecha_efectiva' => $comun['fecha_efectiva']], $comun['operacion_id'], $comun['operacion_hash']);
        } catch (UniqueConstraintViolationException $e) {
            throw str_contains($e->getMessage(), 'offboarding_expedientes.vinculo_id') ? new SalidaYaFormalizada : new ConflictoDeUnicidad;
        }

        return $this->porId($tenantId, $id) ?? throw new ConflictoDeUnicidad;
    }

    public function actualizar(Expediente $actual, array $columnas, string $evento, ?int $actorId, array $detalle = [], ?string $operacionId = null, ?string $operacionHash = null): Expediente
    {
        try {
            $this->subirVersion($actual, $columnas, $actorId);
            $this->evento($actual->tenantId, $actual->id, $evento, $actorId, $detalle, $operacionId, $operacionHash);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->porId($actual->tenantId, $actual->id) ?? throw new ExpedienteModificado;
    }

    public function completarTarea(Expediente $actual, int $tareaId, int $actorId, array $documentoIds, ?string $operacionId, ?string $operacionHash): Expediente
    {
        $ahora = now();

        try {
            $this->subirVersion($actual, [], $actorId);
            $filas = DB::table('offboarding_tareas')->where('id', $tareaId)->where('expediente_id', $actual->id)->where('estado', 'Pendiente')
                ->update(['estado' => 'Completada', 'completada_por' => $actorId, 'completada_en' => $ahora]);
            if ($filas !== 1) {
                throw new ExpedienteModificado;
            }
            foreach (array_values(array_unique($documentoIds)) as $documentoId) {
                DB::table('offboarding_documentos')->insert(['expediente_id' => $actual->id, 'tarea_id' => $tareaId, 'documento_id' => $documentoId, 'tenant_id' => $actual->tenantId, 'created_at' => $ahora]);
            }
            $this->evento($actual->tenantId, $actual->id, 'TareaCompletada', $actorId, ['tarea_id' => $tareaId, 'respaldos' => count($documentoIds)], $operacionId, $operacionHash);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->porId($actual->tenantId, $actual->id) ?? throw new ExpedienteModificado;
    }

    public function guardarRespuestas(Expediente $actual, int $versionDeEntrevista, array $filas, int $actorId, ?string $operacionId, ?string $operacionHash): Expediente
    {
        $entrevistaId = (int) $actual->entrevista['id'];
        $this->actualizarEntrevista($actual, $entrevistaId, $versionDeEntrevista, ['estado' => 'EnCurso']);

        DB::table('offboarding_respuestas')->where('entrevista_id', $entrevistaId)->delete();
        $ahora = now();
        foreach ($filas as $fila) {
            DB::table('offboarding_respuestas')->insert($fila + [
                'tenant_id' => $actual->tenantId, 'entrevista_id' => $entrevistaId, 'cuestionario_version' => $actual->entrevista['cuestionario_version'], 'created_at' => $ahora,
            ]);
        }

        try {
            $this->evento($actual->tenantId, $actual->id, 'EntrevistaGuardada', $actorId, ['respondidas' => count($filas)], $operacionId, $operacionHash);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->porId($actual->tenantId, $actual->id) ?? throw new ExpedienteModificado;
    }

    public function resolverEntrevista(Expediente $actual, int $versionDeEntrevista, array $columnas, string $evento, ?int $actorId, ?string $operacionId, ?string $operacionHash): Expediente
    {
        $ahora = now();
        $entrevistaId = (int) $actual->entrevista['id'];

        try {
            $this->actualizarEntrevista($actual, $entrevistaId, $versionDeEntrevista, $columnas);
            DB::table('offboarding_tareas')->where('expediente_id', $actual->id)->where('tipo', 'Entrevista')->where('estado', 'Pendiente')
                ->update(['estado' => 'Completada', 'completada_por' => $actorId, 'completada_en' => $ahora]);
            $this->subirVersion($actual, [], $actorId);
            $this->evento($actual->tenantId, $actual->id, $evento, $actorId, ['estado' => $columnas['estado'] ?? null, 'motivo' => $columnas['motivo_no_realizada'] ?? null], $operacionId, $operacionHash);
        } catch (UniqueConstraintViolationException) {
            throw new ConflictoDeUnicidad;
        }

        return $this->porId($actual->tenantId, $actual->id) ?? throw new ExpedienteModificado;
    }

    public function respuestasDe(int $tenantId, int $entrevistaId): array
    {
        return DB::table('offboarding_respuestas')->where('tenant_id', $tenantId)->where('entrevista_id', $entrevistaId)->orderBy('id')->get()
            ->map(fn ($f) => [
                'pregunta_id' => (string) $f->pregunta_id, 'tipo' => (string) $f->tipo, 'dimension_o_categoria' => (string) $f->dimension_o_categoria,
                'valor' => $f->valor === null ? null : (int) $f->valor, 'texto' => $f->texto === null ? null : (string) $f->texto,
            ])->all();
    }

    public function registrarEvento(int $tenantId, int $expedienteId, string $evento, ?int $actorId, array $detalle = []): void
    {
        $this->evento($tenantId, $expedienteId, $evento, $actorId, $detalle);
    }

    public function eventos(int $tenantId, int $expedienteId): array
    {
        $filas = DB::table('offboarding_eventos')->where('tenant_id', $tenantId)->where('expediente_id', $expedienteId)->orderBy('id')->get();
        $nombres = $this->nombresDeCuentas($filas->pluck('actor_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all());

        return $filas->map(fn ($f) => [
            'evento' => (string) $f->evento,
            'actor' => $f->actor_tipo === 'sistema'
                ? ['tipo' => 'sistema', 'id' => null, 'nombre' => 'Sistema']
                : ['tipo' => 'usuario', 'id' => (int) $f->actor_id, 'nombre' => $nombres[(int) $f->actor_id] ?? null],
            'detalle' => $f->detalle === null ? null : json_decode((string) $f->detalle, true),
            'en' => $this->iso($f->created_at),
        ])->all();
    }

    public function listar(int $tenantId, array $filtros, int $pagina, int $porPagina): array
    {
        $consulta = DB::table('offboarding_expedientes as e')
            ->join('colaboradores as c', 'c.id', '=', 'e.colaborador_id')
            ->join('offboarding_entrevistas as n', 'n.expediente_id', '=', 'e.id')
            ->where('e.tenant_id', $tenantId);

        $this->aplicarFiltros($consulta, $filtros);
        $total = (clone $consulta)->count('e.id');

        $filas = $consulta->orderByDesc('e.fecha_efectiva')->orderByDesc('e.id')->forPage($pagina, $porPagina)->get([
            'e.id', 'e.colaborador_id', 'e.motivo', 'e.fecha_efectiva', 'e.estado', 'e.cierre_estado', 'e.cuenta_estado', 'e.version',
            'c.codigo', 'c.nombres', 'c.apellidos', 'n.estado as entrevista_estado',
        ]);
        $pendientes = DB::table('offboarding_tareas')->whereIn('expediente_id', $filas->pluck('id')->all())->where('estado', 'Pendiente')
            ->selectRaw('expediente_id, count(*) as total')->groupBy('expediente_id')->pluck('total', 'expediente_id');

        return [
            'items' => $filas->map(fn ($f) => [
                'id' => (int) $f->id,
                'colaborador' => ['id' => (int) $f->colaborador_id, 'codigo' => (string) $f->codigo, 'nombre' => trim($f->nombres.' '.$f->apellidos)],
                'motivo' => (string) $f->motivo,
                'fecha_efectiva' => (string) $f->fecha_efectiva,
                'estado' => (string) $f->estado,
                'cierre_estado' => (string) $f->cierre_estado,
                'cuenta_estado' => (string) $f->cuenta_estado,
                'entrevista_estado' => (string) $f->entrevista_estado,
                'tareas_pendientes' => (int) ($pendientes[$f->id] ?? 0),
                'version' => (int) $f->version,
            ])->all(),
            'total' => $total,
        ];
    }

    public function resumen(int $tenantId): array
    {
        $porEstado = DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $entrevistas = DB::table('offboarding_entrevistas as n')->join('offboarding_expedientes as e', 'e.id', '=', 'n.expediente_id')
            ->where('n.tenant_id', $tenantId)->where('e.estado', '<>', 'Cancelada')->selectRaw('n.estado, count(*) as total')->groupBy('n.estado')->pluck('total', 'n.estado');

        return [
            'programadas' => (int) ($porEstado['Programada'] ?? 0),
            'efectivas' => (int) ($porEstado['Efectiva'] ?? 0),
            'con_incidencia' => (int) ($porEstado['IncidenciaDeAplicacion'] ?? 0),
            'cierre_pendiente' => (int) DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->where('estado', 'Efectiva')->where('cierre_estado', 'Pendiente')->count(),
            'entrevistas_pendientes' => (int) (($entrevistas['Pendiente'] ?? 0) + ($entrevistas['EnCurso'] ?? 0)),
            'entrevistas_completadas' => (int) ($entrevistas['Completada'] ?? 0),
            'entrevistas_no_realizadas' => (int) ($entrevistas['NoRealizada'] ?? 0),
        ];
    }

    public function datosParaAgregados(int $tenantId): array
    {
        $base = DB::table('offboarding_entrevistas as n')->join('offboarding_expedientes as e', 'e.id', '=', 'n.expediente_id')
            ->where('n.tenant_id', $tenantId)->where('n.estado', 'Completada')->where('e.estado', '<>', 'Cancelada');

        return [
            'completadas' => (int) (clone $base)->count('n.id'),
            'respuestas' => DB::table('offboarding_respuestas as r')->joinSub((clone $base)->select('n.id'), 'x', 'x.id', '=', 'r.entrevista_id')
                ->get(['r.entrevista_id', 'r.pregunta_id', 'r.tipo', 'r.valor'])
                ->map(fn ($f) => ['entrevista_id' => (int) $f->entrevista_id, 'pregunta_id' => (string) $f->pregunta_id, 'tipo' => (string) $f->tipo, 'valor' => $f->valor === null ? null : (int) $f->valor])->all(),
        ];
    }

    public function tenantsConSalidasPorAplicar(): array
    {
        return DB::table('offboarding_expedientes')->where('estado', 'Programada')->distinct()->orderBy('tenant_id')->pluck('tenant_id')->map(fn ($id) => (int) $id)->all();
    }

    public function salidasPorAplicar(int $tenantId, string $hastaFecha, int $limite): array
    {
        return DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->where('estado', 'Programada')->where('fecha_efectiva', '<=', $hastaFecha)
            ->orderBy('fecha_efectiva')->orderBy('id')->limit($limite)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function hechosEfectivos(int $tenantId, int $colaboradorId): array
    {
        return DB::table('offboarding_expedientes')->where('tenant_id', $tenantId)->where('colaborador_id', $colaboradorId)->where('estado', 'Efectiva')
            ->orderBy('fecha_efectiva')->get()->map(fn ($f) => [
                'expediente_id' => (int) $f->id, 'vinculo_id' => (int) $f->vinculo_id, 'fecha_efectiva' => (string) $f->fecha_efectiva,
                'motivo' => (string) $f->motivo, 'politica_numero' => (int) $f->politica_numero, 'aplicada_en' => $this->iso($f->aplicada_en),
            ])->all();
    }

    public function fichaResumen(int $tenantId, int $colaboradorId): ?array
    {
        $fila = DB::table('colaboradores')->where('tenant_id', $tenantId)->where('id', $colaboradorId)->first(['codigo', 'nombres', 'apellidos']);

        return $fila === null ? null : ['codigo' => (string) $fila->codigo, 'nombre' => trim($fila->nombres.' '.$fila->apellidos)];
    }

    public function nombresDeCuentas(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id')->mapWithKeys(fn ($n, $id) => [(int) $id => (string) $n])->all();
    }

    // ------------------------------------------------------------------ Apoyo

    /** @param array<string, mixed> $columnas */
    private function subirVersion(Expediente $actual, array $columnas, ?int $actorId): void
    {
        $filas = DB::table('offboarding_expedientes')->where('id', $actual->id)->where('tenant_id', $actual->tenantId)->where('version', $actual->version)
            ->update($columnas + ['version' => DB::raw('version + 1'), 'updated_by' => $actorId ?? 0, 'updated_at' => now()]);
        if ($filas !== 1) {
            throw new ExpedienteModificado;
        }
    }

    /** @param array<string, mixed> $columnas */
    private function actualizarEntrevista(Expediente $actual, int $entrevistaId, int $versionDeEntrevista, array $columnas): void
    {
        $filas = DB::table('offboarding_entrevistas')->where('id', $entrevistaId)->where('tenant_id', $actual->tenantId)
            ->where('version', $versionDeEntrevista)->whereIn('estado', ['Pendiente', 'EnCurso'])
            ->update($columnas + ['version' => DB::raw('version + 1'), 'updated_at' => now()]);
        if ($filas === 1) {
            return;
        }

        $estado = DB::table('offboarding_entrevistas')->where('id', $entrevistaId)->value('estado');
        throw in_array($estado, ['Completada', 'NoRealizada'], true) ? new EntrevistaCerrada : new ExpedienteModificado;
    }

    /** @param array<string, mixed> $detalle */
    private function evento(int $tenantId, int $expedienteId, string $evento, ?int $actorId, array $detalle, ?string $operacionId = null, ?string $operacionHash = null): void
    {
        DB::table('offboarding_eventos')->insert([
            'tenant_id' => $tenantId, 'expediente_id' => $expedienteId, 'evento' => $evento,
            'actor_tipo' => $actorId === null ? 'sistema' : 'usuario', 'actor_id' => $actorId,
            'detalle' => $detalle === [] ? null : json_encode($detalle, JSON_UNESCAPED_UNICODE),
            'operacion_id' => $operacionId, 'operacion_hash' => $operacionHash, 'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $filtros */
    private function aplicarFiltros(Builder $consulta, array $filtros): void
    {
        foreach (['estado' => 'e.estado', 'cierre' => 'e.cierre_estado', 'motivo' => 'e.motivo', 'entrevista' => 'n.estado'] as $clave => $columna) {
            if (! empty($filtros[$clave])) {
                $consulta->where($columna, $filtros[$clave]);
            }
        }
        if (! empty($filtros['colaborador_id'])) {
            $consulta->where('e.colaborador_id', (int) $filtros['colaborador_id']);
        }
        if (! empty($filtros['desde'])) {
            $consulta->where('e.fecha_efectiva', '>=', $filtros['desde']);
        }
        if (! empty($filtros['hasta'])) {
            $consulta->where('e.fecha_efectiva', '<=', $filtros['hasta']);
        }
    }

    private function hidratar(object $fila): Expediente
    {
        $id = (int) $fila->id;
        $documentos = DB::table('offboarding_documentos')->where('expediente_id', $id)->orderBy('documento_id')->get()->groupBy('tarea_id');
        $tareas = DB::table('offboarding_tareas')->where('expediente_id', $id)->orderBy('orden')->get()->map(fn ($t) => [
            'id' => (int) $t->id, 'orden' => (int) $t->orden, 'tipo' => (string) $t->tipo, 'nombre' => (string) $t->nombre,
            'descripcion' => $t->descripcion === null ? null : (string) $t->descripcion, 'exige_evidencia' => (bool) $t->exige_evidencia,
            'estado' => (string) $t->estado, 'completada_por' => $t->completada_por === null ? null : (int) $t->completada_por,
            'completada_en' => $t->completada_en === null ? null : $this->iso($t->completada_en),
            'documento_ids' => ($documentos[$t->id] ?? collect())->map(fn ($d) => (int) $d->documento_id)->values()->all(),
        ])->all();
        $n = DB::table('offboarding_entrevistas')->where('expediente_id', $id)->first();

        return new Expediente(
            $id, (int) $fila->tenant_id, (int) $fila->colaborador_id, (int) $fila->vinculo_id,
            MotivoDeSalida::from((string) $fila->motivo), (string) $fila->fecha_efectiva, EstadoDeSalida::from((string) $fila->estado),
            (string) $fila->cierre_estado, $fila->cierre_por === null ? null : (int) $fila->cierre_por, $fila->cierre_en === null ? null : $this->iso($fila->cierre_en),
            (string) $fila->cuenta_estado, (int) $fila->politica_version_id, (int) $fila->politica_numero, (array) json_decode((string) $fila->politica_condiciones, true),
            (int) $fila->formalizado_por, $this->iso($fila->formalizado_en),
            $fila->cancelacion_razon === null ? null : (string) $fila->cancelacion_razon, $fila->cancelacion_en === null ? null : $this->iso($fila->cancelacion_en),
            $fila->aplicada_en === null ? null : $this->iso($fila->aplicada_en), (bool) $fila->aplicada_por_sistema,
            $fila->incidencia_codigo === null ? null : (string) $fila->incidencia_codigo, $fila->incidencia_detalle === null ? null : (string) $fila->incidencia_detalle,
            (int) $fila->intentos_aplicacion, (int) $fila->version, $tareas,
            [
                'id' => (int) $n->id, 'estado' => EstadoDeEntrevista::from((string) $n->estado), 'cuestionario_version' => (int) $n->cuestionario_version,
                'presentada_en' => $n->presentada_en === null ? null : $this->iso($n->presentada_en),
                'finalizada_en' => $n->finalizada_en === null ? null : $this->iso($n->finalizada_en),
                'motivo_no_realizada' => $n->motivo_no_realizada === null ? null : (string) $n->motivo_no_realizada,
                'no_realizada_por' => $n->no_realizada_por === null ? null : (int) $n->no_realizada_por,
                'no_realizada_en' => $n->no_realizada_en === null ? null : $this->iso($n->no_realizada_en),
                'version' => (int) $n->version,
            ],
            ($documentos[0] ?? collect())->map(fn ($d) => (int) $d->documento_id)->values()->all(),
        );
    }

    private function iso(mixed $valor): string
    {
        return Carbon::parse((string) $valor, 'UTC')->toIso8601ZuluString();
    }
}
