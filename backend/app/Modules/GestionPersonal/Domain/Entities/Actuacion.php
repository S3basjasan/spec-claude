<?php

namespace App\Modules\GestionPersonal\Domain\Entities;

use App\Modules\GestionPersonal\Domain\ValueObjects\EstadoDeActuacion;
use App\Modules\GestionPersonal\Domain\ValueObjects\TipoDeActuacion;

/**
 * Solicitud o registro de personal: estado actual y campos comunes, más el detalle propio de su categoría.
 * La política conservada (versión y copia de condiciones) no cambia después de presentarla.
 */
final class Actuacion
{
    /**
     * @param  array<string, mixed>  $politicaCondiciones  Copia sellada de la versión aplicada al presentar.
     * @param  array{fecha_inicio: string, fecha_fin: string, ocurrida: bool, cruce_reconocido_por: int|null, cruce_reconocido_en: string|null, revision_observacion: string|null}|null  $novedad
     * @param  array<string, mixed>|null  $movimiento  Columnas de `movimientos_personal` ya tipadas.
     * @param  list<int>  $documentoIds
     */
    public function __construct(
        public readonly int $id,
        public readonly int $tenantId,
        public readonly int $colaboradorId,
        public readonly int $vinculoId,
        public readonly TipoDeActuacion $tipo,
        public readonly EstadoDeActuacion $estado,
        public readonly ?string $motivo,
        public readonly int $presentadaPor,
        public readonly bool $enNombreDeColaborador,
        public readonly string $presentadaEn,
        public readonly string $fechaPresentacion,
        public readonly int $politicaVersionId,
        public readonly int $politicaNumero,
        public readonly array $politicaCondiciones,
        public readonly string $politicaHash,
        public readonly int $version,
        public readonly ?array $novedad,
        public readonly ?array $movimiento,
        public readonly array $documentoIds,
    ) {}

    public function fechaEfectiva(): ?string
    {
        return $this->movimiento['fecha_efectiva'] ?? null;
    }

    /** Parámetros de la política conservada (nunca los de la configuración actual). */
    public function parametros(): array
    {
        $parametros = $this->politicaCondiciones['parametros'] ?? [];

        return is_array($parametros) ? $parametros : [];
    }
}
