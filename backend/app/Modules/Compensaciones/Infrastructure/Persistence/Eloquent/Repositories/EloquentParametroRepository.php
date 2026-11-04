<?php

namespace App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Compensaciones\Domain\Entities\CambioDeParametro;
use App\Modules\Compensaciones\Domain\Repositories\ParametroRepository;
use App\Modules\Compensaciones\Domain\ValueObjects\ClaveDeParametro;
use App\Modules\Compensaciones\Infrastructure\Persistence\Eloquent\Models\ParametroModuloModel;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class EloquentParametroRepository implements ParametroRepository
{
    private const CANDADO = 'compensaciones.parametros_modulo';

    public function enTransaccion(callable $accion): mixed
    {
        // El candado cubre también el caso «nunca cambió» (version 0), en que no hay fila que bloquear.
        return Cache::lock(self::CANDADO, 10)->block(5, fn () => DB::transaction($accion));
    }

    public function ultima(ClaveDeParametro $clave): ?CambioDeParametro
    {
        return $this->aEntidad(
            ParametroModuloModel::query()->where('clave', $clave->value)->orderByDesc('id')->first(),
        );
    }

    public function vigenteEn(ClaveDeParametro $clave, string $fecha): ?CambioDeParametro
    {
        return $this->aEntidad(
            ParametroModuloModel::query()
                ->where('clave', $clave->value)
                ->where('vigente_desde', '<=', $fecha)
                ->orderByDesc('vigente_desde')
                ->orderByDesc('id')
                ->first(),
        );
    }

    public function programadaDespuesDe(ClaveDeParametro $clave, string $fecha): ?CambioDeParametro
    {
        return $this->aEntidad(
            ParametroModuloModel::query()
                ->where('clave', $clave->value)
                ->where('vigente_desde', '>', $fecha)
                ->orderByDesc('id')
                ->first(),
        );
    }

    public function anteriorA(CambioDeParametro $cambio): ?CambioDeParametro
    {
        return $this->aEntidad(
            ParametroModuloModel::query()
                ->where('clave', $cambio->clave->value)
                ->where('id', '<', $cambio->id)
                ->orderByDesc('id')
                ->first(),
        );
    }

    public function insertar(ClaveDeParametro $clave, int $valor, string $vigenteDesde, int $actorId, DateTimeImmutable $ahora): CambioDeParametro
    {
        $fila = new ParametroModuloModel([
            'clave' => $clave->value,
            'valor' => $valor,
            'vigente_desde' => $vigenteDesde,
            'created_by' => $actorId,
        ]);
        // Eloquent guarda la hora tal cual: se pasa a la zona de la aplicación para que se lea igual en cualquier zona.
        $fila->created_at = Carbon::instance($ahora)->setTimezone((string) config('app.timezone'));
        $fila->save();

        return $this->aEntidad($fila->refresh()) ?? throw new \LogicException('La fila recién insertada no se pudo leer.');
    }

    private function aEntidad(?ParametroModuloModel $fila): ?CambioDeParametro
    {
        if ($fila === null) {
            return null;
        }

        return new CambioDeParametro(
            (int) $fila->id,
            ClaveDeParametro::from($fila->clave),
            (int) $fila->valor,
            $fila->vigente_desde instanceof \DateTimeInterface ? $fila->vigente_desde->format('Y-m-d') : substr((string) $fila->vigente_desde, 0, 10),
            (int) $fila->created_by,
            DateTimeImmutable::createFromInterface($fila->created_at),
        );
    }
}
