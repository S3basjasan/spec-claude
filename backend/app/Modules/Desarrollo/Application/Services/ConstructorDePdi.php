<?php

namespace App\Modules\Desarrollo\Application\Services;

use App\Modules\Desarrollo\Domain\Entities\AccionDePdi;
use App\Modules\Desarrollo\Domain\Entities\CompromisoDePdi;
use App\Modules\Desarrollo\Domain\Entities\HitoDePdi;
use App\Modules\Desarrollo\Domain\Entities\Pdi;
use App\Modules\Desarrollo\Domain\ValueObjects\DimensionDeCompromiso;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDeHito;
use App\Modules\Desarrollo\Domain\ValueObjects\EstadoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\PeriodoDePdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDeAccionPdi;
use App\Modules\Desarrollo\Domain\ValueObjects\TipoDePdi;
use DateTimeImmutable;

/**
 * Convierte los datos ya validados por la petición (arreglos) en la entidad `Pdi` en Borrador, con sus compromisos,
 * acciones e hitos. No decide reglas de negocio: eso lo hacen `ReglasDeCompromiso` y `ValidadorDePdi`.
 */
final class ConstructorDePdi
{
    /**
     * @param  array<string, mixed>  $datos  cuerpo de crear o actualizar
     */
    public function deDatos(int $tenantId, int $actorId, TipoDePdi $tipo, int $colaboradorId, array $datos): Pdi
    {
        $compromisos = [];
        foreach (array_values($datos['compromisos'] ?? []) as $i => $c) {
            $compromisos[] = $this->compromiso($i + 1, $c);
        }

        $hitos = [];
        foreach (array_values($datos['hitos'] ?? []) as $h) {
            $hitos[] = new HitoDePdi(null, $this->fecha($h['fecha']), trim((string) ($h['descripcion'] ?? '')), EstadoDeHito::Pendiente, null);
        }

        $origen = $datos['origen'] ?? null;

        return new Pdi(
            null, $tenantId, null, $colaboradorId, $tipo, trim((string) ($datos['objetivo'] ?? '')),
            $tipo->requierePuestoObjetivo() ? ($datos['puesto_objetivo_id'] ?? null) : null,
            ($origen['tipo'] ?? null) === 'dnf' ? (int) $origen['id'] : null,
            ($origen['tipo'] ?? null) === 'adecuacion' ? (int) $origen['id'] : null,
            (int) ($datos['responsable_id'] ?? $actorId),
            new PeriodoDePdi($this->fecha($datos['fecha_inicio']), $this->fecha($datos['fecha_fin'])),
            EstadoDePdi::Borrador, 1, null, null, null, null, null, null, null, null, $actorId, null,
            $compromisos, $hitos,
        );
    }

    /** @param  array<string, mixed>  $c */
    private function compromiso(int $orden, array $c): CompromisoDePdi
    {
        $acciones = [];
        foreach (array_values($c['acciones'] ?? []) as $j => $a) {
            $acciones[] = new AccionDePdi(
                null, $j + 1, TipoDeAccionPdi::from($a['tipo']), trim((string) ($a['descripcion'] ?? '')),
                $this->textoOpcional($a['persona_cargo'] ?? null),
                $this->fecha($a['fecha_inicio']), $this->fecha($a['fecha_fin']),
                isset($a['plan_accion_id']) ? (int) $a['plan_accion_id'] : null,
                EstadoDeAccionPdi::Pendiente,
            );
        }

        $dimension = DimensionDeCompromiso::from($c['dimension']);

        return new CompromisoDePdi(
            null, $orden, $dimension,
            $dimension->llevaElemento() ? ($c['tipo_elemento'] ?? $dimension->value) : null,
            $dimension->llevaElemento() && isset($c['elemento_id']) ? (int) $c['elemento_id'] : null,
            $dimension->requiereDescripcion() ? $this->textoOpcional($c['descripcion'] ?? null) : null,
            $this->textoOpcional($c['nivel_actual'] ?? null),
            $this->textoOpcional($c['nivel_objetivo'] ?? null),
            $this->textoOpcional($c['indicador'] ?? null),
            $this->fecha($c['fecha_inicio']), $this->fecha($c['fecha_fin']), null, $acciones,
        );
    }

    private function fecha(string $valor): DateTimeImmutable
    {
        return new DateTimeImmutable($valor);
    }

    private function textoOpcional(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }
}
