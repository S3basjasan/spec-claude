<?php

namespace App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\GestionPersonal\Domain\Entities\AccionDePolitica;
use App\Modules\GestionPersonal\Domain\Entities\PoliticaDePersonal;
use App\Modules\GestionPersonal\Domain\Entities\VersionDePolitica;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorExistente;
use App\Modules\GestionPersonal\Domain\Exceptions\BorradorModificado;
use App\Modules\GestionPersonal\Domain\Exceptions\ClaveDeOperacionReutilizada;
use App\Modules\GestionPersonal\Domain\Exceptions\PoliticaNoEncontrada;
use App\Modules\GestionPersonal\Domain\Exceptions\VersionNoRetirable;
use App\Modules\GestionPersonal\Domain\Repositories\PoliticaRepositoryInterface;
use App\Modules\GestionPersonal\Domain\Services\ReglasDePolitica;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDePolitica;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\PoliticaAccionModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\PoliticaPersonalModel;
use App\Modules\GestionPersonal\Infrastructure\Persistence\Eloquent\Models\PoliticaVersionModel;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class EloquentPoliticaRepository implements PoliticaRepositoryInterface
{
    public function asegurarCabeceras(int $tenantId): void
    {
        $existentes = PoliticaPersonalModel::query()->where('tenant_id', $tenantId)->pluck('tipo')->all();
        $ahora = $this->formato(new DateTimeImmutable('now', new DateTimeZone('UTC')));

        foreach (TipoDePolitica::cases() as $tipo) {
            if (in_array($tipo->value, $existentes, true)) {
                continue;
            }
            PoliticaPersonalModel::query()->insertOrIgnore([
                'tenant_id' => $tenantId, 'tipo' => $tipo->value, 'ultimo_numero' => 0, 'version' => 1,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }
    }

    public function cabeceras(int $tenantId): array
    {
        $salida = [];
        foreach (PoliticaPersonalModel::query()->where('tenant_id', $tenantId)->get() as $fila) {
            $salida[(string) $fila->tipo] = $this->cabeceraDe($fila);
        }

        return $salida;
    }

    public function cabecera(int $tenantId, TipoDePolitica $tipo): ?PoliticaDePersonal
    {
        $fila = PoliticaPersonalModel::query()->where('tenant_id', $tenantId)->where('tipo', $tipo->value)->first();

        return $fila === null ? null : $this->cabeceraDe($fila);
    }

    public function bloquear(int $tenantId, TipoDePolitica $tipo): PoliticaDePersonal
    {
        // Actualización sin cambio de valor: toma el bloqueo de la fila (y la escritura en SQLite) hasta el commit.
        $filas = PoliticaPersonalModel::query()
            ->where('tenant_id', $tenantId)->where('tipo', $tipo->value)
            ->update(['version' => DB::raw('version')]);

        if ($filas === 0) {
            throw new PoliticaNoEncontrada;
        }

        return $this->cabecera($tenantId, $tipo) ?? throw new PoliticaNoEncontrada;
    }

    public function versionesDe(int $tenantId, array $politicaIds): array
    {
        $salida = array_fill_keys($politicaIds, []);
        if ($politicaIds === []) {
            return $salida;
        }

        $filas = PoliticaVersionModel::query()
            ->where('tenant_id', $tenantId)->whereIn('politica_id', $politicaIds)->whereNull('descartada_en')
            ->orderBy('id')->get();

        foreach ($filas as $fila) {
            $salida[(int) $fila->politica_id][] = $this->versionDe($fila);
        }

        return $salida;
    }

    public function version(int $tenantId, int $politicaId, int $versionId): ?VersionDePolitica
    {
        $fila = PoliticaVersionModel::query()
            ->where('tenant_id', $tenantId)->where('politica_id', $politicaId)->whereKey($versionId)->first();

        return $fila === null ? null : $this->versionDe($fila);
    }

    public function siguienteNumero(int $tenantId, int $politicaId): int
    {
        PoliticaPersonalModel::query()->where('tenant_id', $tenantId)->whereKey($politicaId)
            ->update(['ultimo_numero' => DB::raw('ultimo_numero + 1')]);

        return (int) PoliticaPersonalModel::query()->where('tenant_id', $tenantId)->whereKey($politicaId)->value('ultimo_numero');
    }

    public function crearBorrador(int $tenantId, int $politicaId, int $actorId, string $descripcion, array $parametros, ?int $baseVersionId, DateTimeImmutable $ahora): VersionDePolitica
    {
        try {
            $id = PoliticaVersionModel::query()->insertGetId([
                'tenant_id' => $tenantId, 'politica_id' => $politicaId, 'numero' => null,
                'descripcion' => $descripcion, 'parametros' => ReglasDePolitica::json($parametros),
                'base_version_id' => $baseVersionId, 'version' => 1,
                'created_by' => $actorId, 'updated_by' => $actorId,
                'created_at' => $this->formato($ahora), 'updated_at' => $this->formato($ahora),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existente = PoliticaVersionModel::query()
                ->where('politica_id', $politicaId)->whereNull('publicada_en')->whereNull('descartada_en')->value('id');

            throw new BorradorExistente((int) $existente);
        }

        return $this->version($tenantId, $politicaId, $id) ?? throw new PoliticaNoEncontrada;
    }

    public function actualizarBorrador(int $tenantId, int $versionId, int $versionEsperada, int $actorId, string $descripcion, array $parametros, DateTimeImmutable $ahora): VersionDePolitica
    {
        $filas = PoliticaVersionModel::query()
            ->where('tenant_id', $tenantId)->whereKey($versionId)->where('version', $versionEsperada)
            ->whereNull('publicada_en')->whereNull('descartada_en')
            ->update([
                'descripcion' => $descripcion, 'parametros' => ReglasDePolitica::json($parametros),
                'version' => DB::raw('version + 1'), 'updated_by' => $actorId, 'updated_at' => $this->formato($ahora),
            ]);

        if ($filas === 0) {
            throw new BorradorModificado;
        }

        return $this->porId($tenantId, $versionId);
    }

    public function descartarBorrador(int $tenantId, int $versionId, int $versionEsperada, int $actorId, DateTimeImmutable $ahora): void
    {
        $filas = PoliticaVersionModel::query()
            ->where('tenant_id', $tenantId)->whereKey($versionId)->where('version', $versionEsperada)
            ->whereNull('publicada_en')->whereNull('descartada_en')
            ->update([
                'descartada_en' => $this->formato($ahora), 'descartada_por' => $actorId,
                'updated_by' => $actorId, 'updated_at' => $this->formato($ahora),
            ]);

        if ($filas === 0) {
            throw new BorradorModificado;
        }
    }

    public function publicar(
        int $tenantId,
        int $versionId,
        int $versionEsperada,
        int $numero,
        int $actorId,
        DateTimeImmutable $ahora,
        DateTimeImmutable $inicioVigencia,
        string $motivo,
        string $revisor,
        ?string $observaciones,
        array $parametros,
        array $condiciones,
        string $condicionesJson,
        string $hash,
    ): VersionDePolitica {
        $filas = PoliticaVersionModel::query()
            ->where('tenant_id', $tenantId)->whereKey($versionId)->where('version', $versionEsperada)
            ->whereNull('publicada_en')->whereNull('descartada_en')
            ->update([
                'numero' => $numero, 'parametros' => ReglasDePolitica::json($parametros), 'publicada_en' => $this->formato($ahora), 'publicada_por' => $actorId,
                'inicio_vigencia' => $this->formato($inicioVigencia), 'motivo_cambio' => $motivo,
                'revisor_nombre' => $revisor, 'revision_observaciones' => $observaciones, 'revision_en' => $this->formato($ahora),
                'condiciones' => $condicionesJson, 'condiciones_hash' => $hash,
                'updated_by' => $actorId, 'updated_at' => $this->formato($ahora),
            ]);

        if ($filas === 0) {
            throw new BorradorModificado;
        }

        return $this->porId($tenantId, $versionId);
    }

    public function retirar(int $tenantId, int $versionId, int $actorId, DateTimeImmutable $ahora, string $justificacion): VersionDePolitica
    {
        $filas = PoliticaVersionModel::query()
            ->where('tenant_id', $tenantId)->whereKey($versionId)
            ->whereNotNull('publicada_en')->whereNull('retirada_en')
            ->update([
                'retirada_en' => $this->formato($ahora), 'retirada_por' => $actorId, 'retiro_motivo' => $justificacion,
                'updated_by' => $actorId, 'updated_at' => $this->formato($ahora),
            ]);

        if ($filas === 0) {
            throw new VersionNoRetirable;
        }

        return $this->porId($tenantId, $versionId);
    }

    public function registrarAccion(int $tenantId, int $politicaId, int $versionId, string $accion, int $actorId, ?string $motivo, ?string $operacionId, ?string $operacionHash, DateTimeImmutable $ahora): AccionDePolitica
    {
        try {
            $id = PoliticaAccionModel::query()->insertGetId([
                'tenant_id' => $tenantId, 'politica_id' => $politicaId, 'version_id' => $versionId, 'accion' => $accion,
                'actor_id' => $actorId, 'motivo' => $motivo, 'operacion_id' => $operacionId, 'operacion_hash' => $operacionHash,
                'created_at' => $this->formato($ahora),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new ClaveDeOperacionReutilizada;
        }

        return $this->accionDe(PoliticaAccionModel::query()->findOrFail($id));
    }

    public function accionPorOperacion(int $tenantId, string $operacionId): ?AccionDePolitica
    {
        $fila = PoliticaAccionModel::query()->where('tenant_id', $tenantId)->where('operacion_id', $operacionId)->first();

        return $fila === null ? null : $this->accionDe($fila);
    }

    private function porId(int $tenantId, int $versionId): VersionDePolitica
    {
        $fila = PoliticaVersionModel::query()->where('tenant_id', $tenantId)->whereKey($versionId)->firstOrFail();

        return $this->versionDe($fila);
    }

    private function cabeceraDe(PoliticaPersonalModel $fila): PoliticaDePersonal
    {
        return new PoliticaDePersonal(
            (int) $fila->id, (int) $fila->tenant_id, TipoDePolitica::from((string) $fila->tipo),
            (int) $fila->ultimo_numero, (int) $fila->version,
        );
    }

    private function versionDe(PoliticaVersionModel $f): VersionDePolitica
    {
        /** @var array<string, mixed> $parametros */
        $parametros = json_decode((string) $f->parametros, true) ?? [];
        /** @var array<string, mixed>|null $condiciones */
        $condiciones = $f->condiciones === null ? null : json_decode((string) $f->condiciones, true);

        return new VersionDePolitica(
            id: (int) $f->id,
            tenantId: (int) $f->tenant_id,
            politicaId: (int) $f->politica_id,
            numero: $f->numero === null ? null : (int) $f->numero,
            descripcion: (string) $f->descripcion,
            parametros: $parametros,
            baseVersionId: $f->base_version_id === null ? null : (int) $f->base_version_id,
            version: (int) $f->version,
            createdBy: (int) $f->created_by,
            updatedBy: (int) $f->updated_by,
            createdAt: $this->instante((string) $f->created_at),
            updatedAt: $this->instante((string) $f->updated_at),
            descartadaEn: $this->instanteONulo($f->descartada_en),
            descartadaPor: $f->descartada_por === null ? null : (int) $f->descartada_por,
            publicadaEn: $this->instanteONulo($f->publicada_en),
            publicadaPor: $f->publicada_por === null ? null : (int) $f->publicada_por,
            inicioVigencia: $this->instanteONulo($f->inicio_vigencia),
            motivoCambio: $f->motivo_cambio,
            revisorNombre: $f->revisor_nombre,
            revisionObservaciones: $f->revision_observaciones,
            revisionEn: $this->instanteONulo($f->revision_en),
            condiciones: $condiciones,
            condicionesHash: $f->condiciones_hash,
            retiradaEn: $this->instanteONulo($f->retirada_en),
            retiradaPor: $f->retirada_por === null ? null : (int) $f->retirada_por,
            retiroMotivo: $f->retiro_motivo,
        );
    }

    private function accionDe(PoliticaAccionModel $f): AccionDePolitica
    {
        return new AccionDePolitica(
            (int) $f->id, (int) $f->tenant_id, (int) $f->politica_id, (int) $f->version_id, (string) $f->accion,
            (int) $f->actor_id, $f->motivo, $f->operacion_id, $f->operacion_hash, $this->instante((string) $f->created_at),
        );
    }

    private function formato(DateTimeImmutable $instante): string
    {
        return $instante->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    private function instante(string $valor): DateTimeImmutable
    {
        return new DateTimeImmutable($valor, new DateTimeZone('UTC'));
    }

    private function instanteONulo(mixed $valor): ?DateTimeImmutable
    {
        return $valor === null ? null : $this->instante((string) $valor);
    }
}
